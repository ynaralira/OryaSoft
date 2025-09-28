// meeting-room.js - Lógica da sala de reunião WebRTC customizada

// --- VARIÁVEIS E ELEMENTOS ---
const videosGrid = document.getElementById('videosGrid');
const videoError = document.getElementById('videoError');
const toggleCameraBtn = document.getElementById('toggleCameraBtn');
const toggleMicBtn = document.getElementById('toggleMicBtn');
const iconCamera = document.getElementById('iconCamera');
const iconMic = document.getElementById('iconMic');
const labelCamera = document.getElementById('labelCamera');
const labelMic = document.getElementById('labelMic');
const room = window.meetingRoomId || '';

// Cria dinamicamente o vídeo local e placeholder se não existirem
let localVideo = document.getElementById('localVideo');
let localPlaceholder = document.getElementById('localPlaceholder');
function ensureLocalVideoElements() {
    if (!localVideo) {
        localVideo = document.createElement('video');
        localVideo.id = 'localVideo';
        localVideo.autoplay = true;
        localVideo.muted = true; // Só o local mutado
        localVideo.playsInline = true;
    }
    if (!localPlaceholder) {
        localPlaceholder = document.createElement('div');
        localPlaceholder.id = 'localPlaceholder';
    }
}
ensureLocalVideoElements();
// Gera um ID persistente por aba para o usuário na sala
let user = sessionStorage.getItem('orya_meeting_userid');
if (!user) {
    user = 'u' + Math.random().toString(36).substr(2, 9);
    sessionStorage.setItem('orya_meeting_userid', user);
}
let cameraOn = false;
let micOn = false;
let peers = {};
let peerStreams = {};
// Polite peer: quem tem menor userId é o polite
function isPolite(peerId) {
    return user < peerId;
}
let polling = false;

// --- UI ---
function updateCameraBtn() {
    if (cameraOn) {
        iconCamera.className = 'fas fa-video';
        labelCamera.textContent = 'Desativar Câmera';
        toggleCameraBtn.classList.remove('btn-danger');
        toggleCameraBtn.classList.add('btn-success');
    } else {
        iconCamera.className = 'fas fa-video-slash';
        labelCamera.textContent = 'Ativar Câmera';
        toggleCameraBtn.classList.remove('btn-success');
        toggleCameraBtn.classList.add('btn-danger');
    }
}
function updateMicBtn() {
    if (micOn) {
        iconMic.className = 'fas fa-microphone';
        labelMic.textContent = 'Desativar Microfone';
        toggleMicBtn.classList.remove('btn-danger');
        toggleMicBtn.classList.add('btn-success');
    } else {
        iconMic.className = 'fas fa-microphone-slash';
        labelMic.textContent = 'Ativar Microfone';
        toggleMicBtn.classList.remove('btn-success');
        toggleMicBtn.classList.add('btn-danger');
    }
}

// --- MEDIA ---
let cameraStream = null;
let micStream = null;
async function startCamera(on) {
    ensureLocalVideoElements();
    try {
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
        }
        if (on) {
            cameraStream = await navigator.mediaDevices.getUserMedia({ video: true });
            updateLocalMediaStream();
            localVideo.style.display = '';
            localPlaceholder.style.display = 'none';
            if (!videosGrid.contains(localVideo)) {
                videosGrid.prepend(localVideo);
            }
        } else {
            updateLocalMediaStream();
            localVideo.srcObject = null;
            localVideo.style.display = 'none';
            localPlaceholder.style.display = 'flex';
            if (!videosGrid.contains(localPlaceholder)) {
                videosGrid.prepend(localPlaceholder);
            }
        }
        renegotiateAllPeers();
    } catch (err) {
        videoError.style.display = 'block';
        let msg = 'Erro ao acessar câmera: ' + err.message;
        if (err.name === 'NotAllowedError') {
            msg += ' (Permissão negada)';
        } else if (err.name === 'NotFoundError') {
            msg += ' (Nenhum dispositivo encontrado)';
        }
        videoError.textContent = msg;
    }
}
async function startMic(on) {
    try {
        if (micStream) {
            micStream.getTracks().forEach(track => track.stop());
            micStream = null;
        }
        if (on) {
            micStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            // Log de depuração: mostrar se a track de áudio está ativa
            if (micStream && micStream.getAudioTracks().length > 0) {
                console.log('[WebRTC] Track de áudio local capturada:', micStream.getAudioTracks()[0]);
            } else {
                console.warn('[WebRTC] Nenhuma track de áudio local capturada');
            }
        }
        updateLocalMediaStream();
        renegotiateAllPeers();
    } catch (err) {
        videoError.style.display = 'block';
        let msg = 'Erro ao acessar microfone: ' + err.message;
        if (err.name === 'NotAllowedError') {
            msg += ' (Permissão negada)';
        } else if (err.name === 'NotFoundError') {
            msg += ' (Nenhum microfone encontrado)';
        }
        videoError.textContent = msg;
    }
}
function updateLocalMediaStream() {
    let tracks = [];
    if (cameraStream) tracks = tracks.concat(cameraStream.getVideoTracks());
    if (micStream) tracks = tracks.concat(micStream.getAudioTracks());
    if (tracks.length > 0) {
        if (!localVideo.srcObject || !(localVideo.srcObject instanceof MediaStream)) {
            localVideo.srcObject = new MediaStream();
        }
        let ms = localVideo.srcObject;
        ms.getTracks().forEach(t => ms.removeTrack(t));
        tracks.forEach(t => ms.addTrack(t));
        localVideo.style.display = '';
        localPlaceholder.style.display = 'none';
    } else {
        localVideo.srcObject = new MediaStream();
        localVideo.style.display = 'none';
        localPlaceholder.style.display = 'flex';
    }
    Object.values(peers).forEach(pc => {
        let videoTrack = cameraStream ? cameraStream.getVideoTracks()[0] : null;
        let senderV = pc.getSenders().find(s => s.track && s.track.kind === 'video');
        if (senderV) {
            try { senderV.replaceTrack(videoTrack); } catch(e){}
        } else if (videoTrack) {
            try { pc.addTrack(videoTrack, cameraStream); } catch(e){}
        }
        let audioTrack = micStream ? micStream.getAudioTracks()[0] : null;
        let senderA = pc.getSenders().find(s => s.track && s.track.kind === 'audio');
        if (senderA) {
            try { senderA.replaceTrack(audioTrack); } catch(e){}
        } else if (audioTrack) {
            try { pc.addTrack(audioTrack, micStream); } catch(e){}
        }
    });
}
function renegotiateAllPeers() {
    Object.entries(peers).forEach(([peerId, pc]) => {
        pc.createOffer().then(offer => {
            pc.setLocalDescription(offer);
            signaling('offer', { to: peerId, data: JSON.stringify(offer) });
            signaling('force-offer', { to: peerId });
        });
    });
}

// --- SINALIZAÇÃO AJAX ---
async function signaling(action, extra = {}) {
    const form = new FormData();
    form.append('room', room);
    form.append('user', user);
    form.append('action', action);
    for (const k in extra) form.append(k, extra[k]);
    const res = await fetch('../api/webrtc-signaling.php', { method: 'POST', body: form });
    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch (e) {
        videoError.style.display = 'block';
        videoError.textContent = 'Erro na resposta do servidor de sinalização: ' + text.substring(0, 200);
        throw new Error('Resposta do servidor não é JSON: ' + text);
    }
}

// --- PARTICIPANTES E CARDS ---
let userNames = {};
userNames[user] = 'Você';
function getUserName(peerId) {
    return userNames[peerId] || ('Usuário ' + peerId.substr(-4));
}
function renderParticipants(peersList) {
    videosGrid.innerHTML = '';
    videosGrid.appendChild(createParticipantCard(user, localVideo.srcObject && localVideo.srcObject.getVideoTracks().length > 0 ? localVideo : null, true));
    peersList.forEach(pid => {
        if (pid !== user) {
            let v = peerStreams[pid] && peerStreams[pid].srcObject && peerStreams[pid].srcObject.getVideoTracks().length > 0 ? peerStreams[pid] : null;
            videosGrid.appendChild(createParticipantCard(pid, v, false));
        }
    });
}
function createParticipantCard(pid, videoElem, isLocal) {
    const card = document.createElement('div');
    card.className = 'participant-card';
    card.style.position = 'relative';
    card.style.width = '100%';
    card.style.maxWidth = '340px';
    card.style.minWidth = '180px';
    card.style.height = '220px';
    card.style.background = '#222';
    card.style.borderRadius = '18px';
    card.style.display = 'flex';
    card.style.flexDirection = 'column';
    card.style.alignItems = 'center';
    card.style.justifyContent = 'center';
    card.style.boxShadow = '0 2px 12px rgba(0,0,0,0.10)';
    card.style.overflow = 'hidden';
    if (videoElem) {
        videoElem.style.width = '100%';
        videoElem.style.height = '100%';
        videoElem.style.objectFit = 'cover';
        videoElem.style.border = 'none';
        card.appendChild(videoElem);
    } else {
        const avatar = document.createElement('div');
        avatar.style.width = '80px';
        avatar.style.height = '80px';
        avatar.style.background = '#333';
        avatar.style.borderRadius = '50%';
        avatar.style.display = 'flex';
        avatar.style.alignItems = 'center';
        avatar.style.justifyContent = 'center';
        avatar.style.fontSize = '3rem';
        avatar.style.color = '#C13584';
        avatar.innerHTML = '<i class="fas fa-user-circle"></i>';
        card.appendChild(avatar);
    }
    const name = document.createElement('div');
    name.textContent = getUserName(pid);
    name.style.position = 'absolute';
    name.style.bottom = '10px';
    name.style.left = '0';
    name.style.width = '100%';
    name.style.textAlign = 'center';
    name.style.color = '#fff';
    name.style.fontWeight = 'bold';
    name.style.fontSize = '1.1rem';
    name.style.textShadow = '0 2px 8px #000';
    card.appendChild(name);
    return card;
}

// --- PEERS ---
function createPeerConnection(peerId) {
    console.log('[WebRTC] Criando PeerConnection para', peerId);
    const pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
    if (cameraStream) {
        cameraStream.getVideoTracks().forEach(track => {
            try { pc.addTrack(track, cameraStream); } catch (e) { console.warn('Erro ao adicionar track camera', e); }
        });
    }
    if (micStream) {
        micStream.getAudioTracks().forEach(track => {
            try { pc.addTrack(track, micStream); } catch (e) { console.warn('Erro ao adicionar track mic', e); }
        });
    }
    pc.onicecandidate = e => {
        if (e.candidate) {
            signaling('candidate', { to: peerId, data: JSON.stringify(e.candidate) });
        }
    };
    let makingOffer = false;
    let ignoreOffer = false;
    pc.ontrack = e => {
        console.log('[WebRTC] Recebendo track de', peerId, e.streams);
        if (!peerStreams[peerId]) {
            const v = document.createElement('video');
            v.autoplay = true;
            v.playsInline = true;
            v.muted = false; // Garante que o vídeo remoto NÃO está mutado
            v.volume = 1.0;
            peerStreams[peerId] = v;
        }
        peerStreams[peerId].srcObject = e.streams[0];
        // Força autoplay do vídeo remoto
        setTimeout(() => { try { peerStreams[peerId].play(); } catch(e){} }, 100);
        // Log para depuração de áudio
        const audioTracks = e.streams[0].getAudioTracks();
        if (audioTracks.length > 0) {
            console.log('[WebRTC] Track de áudio recebida de', peerId, audioTracks[0], 'active:', audioTracks[0].enabled, 'muted:', audioTracks[0].muted);
        } else {
            console.warn('[WebRTC] Nenhuma track de áudio recebida de', peerId);
        }
        renderParticipants([user, ...Object.keys(peers)]);
    };
    pc.onconnectionstatechange = () => {
        console.log('[WebRTC] Estado da conexão com', peerId, pc.connectionState);
    };
    // Sinalização de negociação para evitar offer collision
    pc.onnegotiationneeded = async () => {
        try {
            makingOffer = true;
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            signaling('offer', { to: peerId, data: JSON.stringify(offer) });
        } catch (e) {
            // Pode ignorar
        } finally {
            makingOffer = false;
        }
    };
    return pc;
}

// --- POLLING ---
async function pollMessages() {
    polling = true;
    pollingActive = true;
    let lastPeers = [];
    while (pollingActive) {
        try {
            const res = await signaling('poll');
            const currentPeers = res.peers.filter(pid => pid !== user);
            currentPeers.forEach(pid => {
                if (!peers[pid]) {
                    peers[pid] = createPeerConnection(pid);
                    peers[pid].createOffer().then(offer => {
                        peers[pid].setLocalDescription(offer);
                        signaling('offer', { to: pid, data: JSON.stringify(offer) });
                    });
                }
            });
            Object.keys(peers).forEach(pid => {
                if (!res.peers.includes(pid)) {
                    peers[pid].close();
                    if (peerStreams[pid]) {
                        peerStreams[pid].remove();
                        delete peerStreams[pid];
                    }
                    delete peers[pid];
                }
            });
            for (const msg of res.messages) {
                if (!peers[msg.from]) {
                    peers[msg.from] = createPeerConnection(msg.from);
                }
                if (msg.type === 'offer') {
                    console.log('[WebRTC] Recebida offer de', msg.from);
                    const polite = isPolite(msg.from);
                    const pc = peers[msg.from];
                    const offerCollision = pc.signalingState !== 'stable' || pc.makingOffer;
                    if (offerCollision) {
                        if (!polite) {
                            console.warn('[WebRTC] Offer collision: ignorando offer de', msg.from);
                            return;
                        }
                    }
                    pc.setRemoteDescription(new RTCSessionDescription(JSON.parse(msg.data))).then(() => {
                        return pc.createAnswer();
                    }).then(answer => {
                        pc.setLocalDescription(answer);
                        signaling('answer', { to: msg.from, data: JSON.stringify(answer) });
                    }).catch(e => {
                        console.warn('Erro ao processar offer/answer:', e);
                    });
                } else if (msg.type === 'answer') {
                    console.log('[WebRTC] Recebida answer de', msg.from);
                    peers[msg.from].setRemoteDescription(new RTCSessionDescription(JSON.parse(msg.data))).catch(e => {
                        console.warn('Erro ao processar answer:', e);
                    });
                } else if (msg.type === 'candidate') {
                    peers[msg.from].addIceCandidate(new RTCIceCandidate(JSON.parse(msg.data))).catch(e => {
                        console.warn('Erro ao adicionar candidate:', e);
                    });
                } else if (msg.type === 'force-offer') {
                    // Só o polite peer deve responder a force-offer
                    if (isPolite(msg.from)) {
                        peers[msg.from].createOffer().then(offer => {
                            peers[msg.from].setLocalDescription(offer);
                            signaling('offer', { to: msg.from, data: JSON.stringify(offer) });
                        });
                    }
                }
            }
            renderParticipants([user, ...currentPeers]);
            lastPeers = currentPeers;
        } catch (e) { /* ignore */ }
        await new Promise(r => setTimeout(r, 1200));
    }
    polling = false;
}

// --- BOTÕES ---
toggleCameraBtn.onclick = async function() {
    cameraOn = !cameraOn;
    await startCamera(cameraOn);
    updateCameraBtn();
};
toggleMicBtn.onclick = async function() {
    micOn = !micOn;
    await startMic(micOn);
    updateMicBtn();
};


// Função para entrar na sala e iniciar o polling
async function joinRoom() {
    try {
        // Notifica o backend que entrou na sala (opcional, dependendo do backend)
        await signaling('join');
    } catch (e) {
        // Pode ignorar se o backend não exigir
    }
    if (!polling) {
        pollMessages();
    }
}

// Inicialização
startCamera(false);
startMic(false);
updateCameraBtn();
updateMicBtn();
joinRoom();

// --- CSS RESPONSIVO ---
const style = document.createElement('style');
style.textContent = `
.videos-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    justify-content: center;
    align-items: stretch;
    margin-bottom: 18px;
    min-height: 220px;
    background: #181828;
    border-radius: 18px;
    box-shadow: 0 4px 24px rgba(114,47,92,0.10);
    padding: 18px 0;
    overflow-x: auto;
}
.participant-card {
    flex: 1 1 220px;
    max-width: 340px;
    min-width: 160px;
    height: 220px;
    margin: 0 4px;
    background: #222;
    border-radius: 18px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}
@media (max-width: 900px) {
    .videos-grid { flex-direction: column; gap: 12px; padding: 8px 0; }
    .participant-card { max-width: 98vw; min-width: 120px; height: 180px; }
}
@media (max-width: 600px) {
    .videos-grid { flex-direction: column; gap: 8px; padding: 4px 0; }
    .participant-card { max-width: 100vw; min-width: 90px; height: 120px; }
}
`;
document.head.appendChild(style);
