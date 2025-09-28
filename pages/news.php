<?php
$page_title = 'Noticias de Tecnologia';
$page_name = 'Noticias';

require_once '../includes/header.php';
?>
	<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Notícias de Programação BR</title>
<style>
  body {
    font-family: Arial, sans-serif;
    background: #fff; /* fundo branco */
    color: #333;
    margin: 0; padding: 0;
  }
  header {
    background: transparent; /* sem fundo */
    color: #3b0a28; /* vinho escuro */
    padding: 10px 20px;
    text-align: left;
    font-weight: 600;
    font-size: 1.2rem;
    margin-bottom: 10px;
  }
  .news-container {
    max-width: 900px;
    margin: auto;
    padding: 0 20px 40px 20px;
    display: grid;
    grid-template-columns: repeat(auto-fit,minmax(320px,1fr));
    gap: 24px;
  }
  .news-card {
    background: #fafafa;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(59,10,40,0.1);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s ease;
  }
  .news-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 15px rgba(59,10,40,0.15);
  }
  .news-image {
    width: 100%;
    height: 160px;
    object-fit: cover;
    background: #3b0a28;
  }
  .news-content {
    padding: 12px 16px 20px 16px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }
  .news-content h3 {
    margin: 0 0 10px 0;
    font-size: 1rem;
    color: #3b0a28;
  }
  .news-content p.date {
    font-size: 0.75rem;
    color: #7a5e63;
    margin-bottom: 12px;
    font-style: italic;
  }
  .news-content a {
    align-self: flex-start;
    background: #6b1f44;
    color: #f0d9b5;
    padding: 6px 14px;
    border-radius: 20px;
    font-weight: 600;
    text-decoration: none;
    transition: background 0.25s ease;
    font-size: 0.875rem;
  }
  .news-content a:hover {
    background: #a23b5a;
  }
  .loading {
    text-align: center;
    color: #999;
    font-size: 1rem;
    grid-column: 1/-1;
  }
</style>
</head>
<body>

<header>
  Notícias de Programação BR
</header>

<div class="news-container" id="news-container">
  <p class="loading">Carregando notícias...</p>
</div>

<script>
  const feeds = [
    "https://canaltech.com.br/rss/",
    "https://www.tecmundo.com.br/feeds",
    "https://olhardigital.com.br/feed/",
    "https://www.infoq.com/br/development/rss/"
  ];

  const keywords = [
    "programação",
    "programador",
    "desenvolvimento",
    "java",
    "javascript",
    "python",
    "php",
    "ruby",
    "node.js",
    "react",
    "angular",
    "vue",
    "frontend",
    "backend",
    "fullstack",
    "dev",
    "tecnologia"
  ];

  const placeholderImg = "https://via.placeholder.com/400x160/3b0a28/ddd?text=Sem+imagem";

  const newsContainer = document.getElementById("news-container");

  function rssToJson(feedUrl) {
    const encodedUrl = encodeURIComponent(feedUrl);
    return fetch(`https://api.rss2json.com/v1/api.json?rss_url=${encodedUrl}`)
      .then(res => res.json());
  }

  function filtrarNoticias(items) {
    return items.filter(item => {
      const texto = (item.title + " " + (item.description || "")).toLowerCase();
      return keywords.some(key => texto.includes(key));
    });
  }

  function extrairImagem(item) {
    if(item.thumbnail) return item.thumbnail;
    if(item.enclosure && item.enclosure.link) return item.enclosure.link;

    const desc = item.description || "";
    const imgMatch = desc.match(/<img.*?src=["'](.*?)["']/);
    if(imgMatch && imgMatch[1]) return imgMatch[1];

    return placeholderImg;
  }

  async function carregarNoticias() {
    newsContainer.innerHTML = '<p class="loading">Carregando notícias...</p>';
    try {
      const todosFeeds = await Promise.all(feeds.map(rssToJson));
      let todasNoticias = [];
      todosFeeds.forEach(feed => {
        if(feed.items) todasNoticias = todasNoticias.concat(feed.items);
      });

      const noticiasFiltradas = filtrarNoticias(todasNoticias);

      if(noticiasFiltradas.length === 0) {
        newsContainer.innerHTML = "<p>Nenhuma notícia de programação encontrada.</p>";
        return;
      }

      noticiasFiltradas.sort((a,b) => new Date(b.pubDate) - new Date(a.pubDate));

      newsContainer.innerHTML = "";
      noticiasFiltradas.forEach(noticia => {
        const card = document.createElement("div");
        card.classList.add("news-card");

        const imagem = extrairImagem(noticia);
        const dataFormatada = new Date(noticia.pubDate).toLocaleDateString("pt-BR");

        card.innerHTML = `
          <img class="news-image" src="${imagem}" alt="Imagem notícia" />
          <div class="news-content">
            <h3>${noticia.title}</h3>
            <p class="date">${dataFormatada}</p>
            <a href="${noticia.link}" target="_blank" rel="noopener noreferrer">Ler mais</a>
          </div>
        `;

        newsContainer.appendChild(card);
      });

    } catch(err) {
      newsContainer.innerHTML = "<p>Erro ao carregar notícias.</p>";
      console.error(err);
    }
  }

  carregarNoticias();
</script>

</body>
</html>
	<?php 
require_once '../includes/footer.php'; 

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'agora';
    if ($time < 3600) return floor($time/60) . 'm';
    if ($time < 86400) return floor($time/3600) . 'h';
    if ($time < 2592000) return floor($time/86400) . 'd';
    if ($time < 31536000) return floor($time/2592000) . ' meses';
    
    return floor($time/31536000) . ' anos';
}

function getPostTypeLabel($type) {
    $labels = [
        'discussion' => 'Discussão',
        'question' => 'Pergunta',
        'tutorial' => 'Tutorial',
        'project' => 'Projeto',
        'tip' => 'Dica',
        'news' => 'Notícia'
    ];
    
    return $labels[$type] ?? ucfirst($type);
}

function formatPostContent($content) {
    $content = escape($content);
    $content = preg_replace('/(https?:\/\/[^\s]+)/', '<a href="$1" target="_blank" rel="noopener">$1<\/a>', $content);
    $content = preg_replace('/(^|\s)#([\p{L}\p{N}_-]+)/u', '$1<a class="hashtag" href="?tag=$2">#$2<\/a>', $content);
    $content = preg_replace('/(^|\s)@([\p{L}\p{N}\._-]+)/u', '$1<a class="mention" href="?search=%40$2">@$2<\/a>', $content);
 
    $content = nl2br($content);
    
    return $content;
}
?>
