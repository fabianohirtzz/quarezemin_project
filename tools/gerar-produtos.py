"""Gera as 12 páginas de produto (produto-<slug>.html) a partir de
tools/produto-template.html. Header e footer vêm do template; o <main>,
o <title> e a meta description são montados a partir de PRODUTOS.

Uso (na raiz do projeto):  python tools/gerar-produtos.py
"""
import io
import re
from html import escape
from pathlib import Path
from urllib.parse import quote

ROOT = Path(__file__).resolve().parent.parent
TEMPLATE = ROOT / "tools" / "produto-template.html"

IP = "IP Vales da Uva Goethe"
CANTINA = "Cantina em pedra"

PRODUTOS = [
    {
        "slug": "margherita-branco-seco",
        "nome": "Margherita Branco Seco",
        "titulo": "<em>Margherita</em> Branco Seco",
        "linha": "Linha Margherita · 100% Goethe · Seco",
        "rail": IP,
        "premios": ["Medalha de Prata · Brasil Wine Cup 2026"],
        "lede": "Vinho delicado, de coloração amarelo palha com reflexos dourados. "
                "Bouquet finíssimo que combina mel, flores brancas, frutas cítricas e um toque mineral.",
        "notas": "Ao paladar, ótimo equilíbrio entre frescor e maciez, com bom corpo, ótima acidez "
                 "e final agradavelmente mineral. As uvas são cultivadas em Azambuja, Pedras Grandes/SC, "
                 "nos Vales da Uva Goethe, e o vinho leva o selo da Indicação de Procedência. Margherita é "
                 "uma homenagem à mãe do fundador da vinícola, de uma família com tradição centenária na "
                 "produção de uvas e vinhos.",
        "specs": [("Uva", "Goethe"), ("Volume", "750ml"), ("Álcool", "11,5%"), ("Servir", "6 a 9°C")],
        "harmoniza": "Carnes brancas, peixes, frutos do mar, queijos leves, risotos, sushi e pratos agridoces. "
                     "Vai muito bem com porco à Califórnia, ravióli de pera ou figo com gorgonzola e queijo "
                     "brie com mel ou calda de frutas vermelhas.",
        "desc": "Margherita Branco Seco, 100% Goethe: amarelo palha com reflexos dourados, notas de mel, "
                "flores brancas e frutas cítricas. Medalha de Prata no Brasil Wine Cup 2026.",
    },
    {
        "slug": "margherita-demi-sec",
        "nome": "Margherita Demi-Sec",
        "titulo": "<em>Margherita</em> Demi-Sec",
        "linha": "Linha Margherita · 100% Goethe · Demi-sec",
        "rail": IP,
        "premios": ["Medalha de Bronze · Brasil Wine Cup 2026"],
        "lede": "Vinho delicado, de coloração amarelo palha com reflexos dourados. "
                "Bouquet finíssimo que combina mel, flores brancas, frutas cítricas e um toque mineral.",
        "notas": "Ao paladar, equilíbrio entre frescor e maciez, com bom corpo, ótima acidez e final "
                 "agradavelmente mineral. Equilibrado, fresco e alegre. As uvas são cultivadas em Azambuja, "
                 "Pedras Grandes/SC, nos Vales da Uva Goethe, e o vinho leva o selo da Indicação de "
                 "Procedência. Margherita é uma homenagem à mãe do fundador da vinícola.",
        "specs": [("Uva", "Goethe"), ("Volume", "750ml"), ("Álcool", "11,5%"), ("Servir", "6 a 9°C")],
        "harmoniza": "Carnes brancas, peixes, frutos do mar, risotos, pratos agridoces e comidas condimentadas. "
                     "Perfeito com culinária tailandesa e japonesa, como o sushi, em dias quentes, festas e eventos.",
        "desc": "Margherita Demi-Sec, 100% Goethe: frescor e maciez, notas de mel, flores brancas e frutas "
                "cítricas. Medalha de Bronze no Brasil Wine Cup 2026.",
    },
    {
        "slug": "branco-seco-goethe",
        "nome": "Branco Seco Goethe",
        "titulo": "<em>Branco Seco</em> Goethe",
        "linha": "Linha Mesa · Goethe e Niágara · Seco",
        "rail": IP,
        "premios": [],
        "lede": "Corte das uvas Goethe e Niágara, em tonalidade amarelo palha, com aroma intenso e frutado "
                "e um leve toque mineral.",
        "notas": "Produzido pelo método tradicional das antigas vinícolas de meados do século XX, com o mosto "
                 "fermentado junto com as cascas. Sabor fresco e elegante, que traduz de forma descontraída o "
                 "espírito jovem e alegre do novo consumidor de vinhos brasileiros. Uvas cultivadas em "
                 "Azambuja, Pedras Grandes/SC, nos Vales da Uva Goethe.",
        "specs": [("Uva", "Goethe e Niágara"), ("Volume", "750ml"), ("Álcool", "11,5%"), ("Servir", "5 a 8°C")],
        "harmoniza": "Carnes brancas, frango, peixes, frutos do mar, queijos suaves, saladas, patês, sanduíches "
                     "e sushi. Harmonia perfeita para dias e noites quentes, festas e eventos.",
        "desc": "Branco Seco Goethe, vinho de mesa de Goethe e Niágara: amarelo palha, aroma frutado e toque "
                "mineral. Fermentado com as cascas pelo método tradicional.",
    },
    {
        "slug": "branco-suave-goethe",
        "nome": "Branco Suave Goethe",
        "titulo": "<em>Branco Suave</em> Goethe",
        "linha": "Linha Mesa · Goethe e Niágara · Suave",
        "rail": IP,
        "premios": [],
        "lede": "Corte das uvas Goethe e Niágara, em tonalidade amarelo palha, com aroma intenso e frutado "
                "e um leve toque mineral.",
        "notas": "Produzido pelo método tradicional das antigas vinícolas de meados do século XX, com o mosto "
                 "fermentado junto com as cascas. Dulçor equilibrado e sabor fresco, que deixa sensações "
                 "agradáveis na boca. Uvas cultivadas em Azambuja, Pedras Grandes/SC, nos Vales da Uva Goethe.",
        "specs": [("Uva", "Goethe e Niágara"), ("Volume", "750ml"), ("Álcool", "11,5%"), ("Servir", "5 a 8°C")],
        "harmoniza": "Carnes brancas, frango, peixes, frutos do mar, queijos suaves, saladas, patês, sanduíches "
                     "e sushi. Harmonia perfeita para dias e noites quentes, festas e eventos.",
        "desc": "Branco Suave Goethe, vinho de mesa de Goethe e Niágara: amarelo palha, aroma intenso e "
                "frutado, dulçor equilibrado e frescor.",
    },
    {
        "slug": "rose-goethe",
        "nome": "Rosé Goethe",
        "titulo": "<em>Rosé</em> Goethe",
        "linha": "Linha Goethe · 100% Goethe · Demi-sec",
        "rail": IP,
        "premios": ["Medalha de Prata · Concurso Brasileiro de Vinhos de Mesa 2024",
                    "Medalha de Bronze · Brasil Wine Cup 2026"],
        "lede": "Coloração salmão claro. Aromas finos de morango, framboesa e pêssego, com toques cítricos "
                "e minerais.",
        "notas": "Um demi-sec fácil de beber, leve, aromático, frutado e muito prazeroso, com ótima "
                 "complexidade e persistência em boca. Avaliado às cegas entre mais de 170 rótulos nacionais, "
                 "levou a prata no Concurso Brasileiro de Vinhos de Mesa. Uvas Goethe colhidas manualmente "
                 "nos Vales da Uva Goethe.",
        "specs": [("Uva", "Goethe"), ("Volume", "750ml"), ("Álcool", "11%"), ("Servir", "6 a 9°C")],
        "harmoniza": "Entradas, risotos, carnes brancas e frutos do mar, até um jantar com pratos mais "
                     "sofisticados. Acompanha muito bem uma tarde de praia ou piscina.",
        "desc": "Rosé Goethe Demi-sec, cor salmão claro, aromas de morango, framboesa e pêssego. Medalha de "
                "Prata no Concurso Brasileiro de Vinhos de Mesa 2024 e Bronze no Brasil Wine Cup 2026.",
    },
    {
        "slug": "tinto-seco-bordo",
        "nome": "Tinto Seco Bordô",
        "titulo": "<em>Tinto Seco</em> Bordô",
        "linha": "Linha Mesa · Bordô · Seco",
        "rail": CANTINA,
        "premios": [],
        "lede": "Coloração que vai do vermelho vivo ao violeta intenso, com aromas de frutas vermelhas maduras.",
        "notas": "Personalidade marcante, bem encorpado, de sabor frutado e aveludado ao paladar. Um vinho "
                 "típico e agradável, com bom potencial evolutivo, característico dos melhores exemplares "
                 "desta variedade.",
        "specs": [("Uva", "Bordô"), ("Volume", "750ml"), ("Álcool", "11,5%"), ("Servir", "12 a 16°C")],
        "harmoniza": "Carnes em geral, pizzas, massas com molho vermelho condimentado, estrogonofe, pratos à "
                     "base de moranga e feijoada. Com corpo médio e boa fruta, também acompanha pratos de "
                     "legumes sem destoar.",
        "desc": "Tinto Seco Bordô, vinho de mesa encorpado, frutado e aveludado, do vermelho vivo ao violeta "
                "intenso.",
    },
    {
        "slug": "tinto-suave-bordo",
        "nome": "Tinto Suave Bordô",
        "titulo": "<em>Tinto Suave</em> Bordô",
        "linha": "Linha Mesa · Bordô · Suave",
        "rail": CANTINA,
        "premios": [],
        "lede": "Coloração que vai do vermelho vivo ao violeta intenso, com aromas de frutas vermelhas maduras.",
        "notas": "Personalidade marcante, bem encorpado, de sabor frutado e aveludado ao paladar. O dulçor não "
                 "contrasta com o corpo do vinho: os dois somam em complexidade e numa agradável sensação "
                 "em boca.",
        "specs": [("Uva", "Bordô"), ("Volume", "750ml"), ("Álcool", "11,5%"), ("Servir", "12 a 16°C")],
        "harmoniza": "Carnes em geral, pizzas, massas com molho vermelho condimentado, estrogonofe, pratos à "
                     "base de moranga e feijoada. Corpo e doçura o tornam bastante versátil.",
        "desc": "Tinto Suave Bordô, vinho de mesa frutado e aveludado, com dulçor que soma complexidade ao corpo.",
    },
    {
        "slug": "cabernet-sauvignon",
        "nome": "Cabernet Sauvignon",
        "titulo": "<em>Cabernet</em> Sauvignon",
        "linha": "Linha Fino · 100% Cabernet Sauvignon · Seco",
        "rail": CANTINA,
        "premios": [],
        "lede": "Cor vermelho rubi intensa e aroma de frutas vermelhas. Encorpado, de boa estrutura e grande "
                "persistência em boca.",
        "notas": "Paladar ligeiramente tânico, com acidez balanceada e final longo e elegante, com leve "
                 "evolução em madeira de carvalho. Feito para o enófilo que aprecia um vinho de gosto "
                 "agradável e fácil de beber.",
        "specs": [("Uva", "Cabernet Sauvignon"), ("Volume", "750ml"), ("Álcool", "12%"), ("Servir", "16 a 18°C")],
        "harmoniza": "Carnes vermelhas assadas ou grelhadas, frango assado com batatas e cebolas, e queijos.",
        "desc": "Cabernet Sauvignon, vinho tinto seco fino: vermelho rubi intenso, encorpado, com leve "
                "evolução em carvalho.",
    },
    {
        "slug": "merlot",
        "nome": "Merlot",
        "titulo": "<em>Merlot</em>",
        "linha": "Linha Fino · 100% Merlot · Seco",
        "rail": CANTINA,
        "premios": [],
        "lede": "Vermelho rubi, bem estruturado, com taninos macios e ótimo equilíbrio.",
        "notas": "Aroma intenso e frutado, que remete a frutas negras maduras como ameixas e mirtilos. "
                 "Elaborado unicamente com uvas da variedade Merlot.",
        "specs": [("Uva", "Merlot"), ("Volume", "750ml"), ("Álcool", "12,5%"), ("Servir", "16 a 18°C")],
        "harmoniza": "Saborosos pratos de carnes e massas.",
        "desc": "Merlot, vinho tinto seco fino: vermelho rubi, taninos macios e aroma de frutas negras maduras.",
    },
    {
        "slug": "espumante-moscatel-branco",
        "nome": "Espumante Moscatel Branco",
        "titulo": "<em>Moscatel</em> Branco",
        "linha": "Linha Espumante · Moscatel · Branco",
        "rail": CANTINA,
        "premios": [],
        "lede": "Amarelo palha com reflexos esverdeados, borbulhas finas e intensas e aroma floral que lembra "
                "flores brancas, com um toque inconfundível de mel.",
        "notas": "Agradável frescor, baixa graduação alcoólica e perlage persistente, resultado do processo "
                 "natural de fermentação. No paladar, estrutura leve e excelente equilíbrio.",
        "specs": [("Uva", "Moscatéis brancas"), ("Volume", "750ml"), ("Álcool", "7,5%"), ("Servir", "2 a 5°C")],
        "harmoniza": "Sobremesas, tortas, frutas frescas, sorvetes e mousses. Ideal para o happy hour, como "
                     "aperitivo, como base de drinks e coquetéis e, principalmente, para brindar bons momentos.",
        "desc": "Espumante Moscatel Branco: borbulhas finas, aroma floral com toque de mel, frescor e baixa "
                "graduação alcoólica.",
    },
    {
        "slug": "espumante-moscatel-rose",
        "nome": "Espumante Moscatel Rosé",
        "titulo": "<em>Moscatel</em> Rosé",
        "linha": "Linha Espumante · Moscatel · Rosé",
        "rail": CANTINA,
        "premios": [],
        "lede": "Coloração rosada brilhante, de boa intensidade e reflexos rubi. No aroma, frutas vermelhas "
                "frescas como cereja e morango, com notas cítricas.",
        "notas": "Paladar macio e cremoso, com acidez bem equilibrada. Um espumante que agrada aos mais "
                 "variados perfis de apreciadores.",
        "specs": [("Uva", "Moscatéis"), ("Volume", "750ml"), ("Álcool", "7,5%"), ("Servir", "2 a 5°C")],
        "harmoniza": "Sobremesas, tortas, frutas frescas, sorvetes e mousses. Ideal para o happy hour, como "
                     "aperitivo, como base de drinks e coquetéis e, principalmente, para brindar bons momentos.",
        "desc": "Espumante Moscatel Rosé: rosado brilhante, aromas de cereja e morango, paladar macio e cremoso.",
    },
    {
        "slug": "suco-uva-integral",
        "nome": "Suco de Uva Integral",
        "titulo": "<em>Suco</em> de Uva Integral",
        "linha": "Suco de Uva · Isabel e Bordô · Integral",
        "rail": CANTINA,
        "premios": [],
        "lede": "Aspecto vívido e brilhante, coloração vermelho violeta e aroma intenso e fresco que lembra "
                "frutos vermelhos maduros.",
        "notas": "Sabor delicado, frutado, refrescante e doce. Pronto para consumo, sem adição de açúcar e sem "
                 "álcool. Depois de aberto, conserve sob refrigeração (4 a 6°C) e consuma em até 5 dias.",
        "specs": [("Uva", "Isabel e Bordô"), ("Volume", "1 litro"), ("Álcool", "Sem álcool"), ("Servir", "4 a 6°C")],
        "harmoniza": "Uma bebida saborosa e saudável para acompanhar as refeições do dia a dia e pratos leves.",
        "desc": "Suco de Uva Integral Quarezemin, de uvas Isabel e Bordô: sem adição de açúcar e sem álcool.",
    },
]

MEDAL_SVG = ('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
             'stroke-linecap="round" stroke-linejoin="round"><path d="m15.477 12.89 1.515 8.526a.5.5 0 0 1-.81.47'
             'l-3.58-2.687a1 1 0 0 0-1.197 0l-3.586 2.686a.5.5 0 0 1-.81-.469l1.514-8.526"/>'
             '<circle cx="12" cy="8" r="6"/></svg>')
ARROW_SVG = ('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" '
             'stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>')


def card(p):
    meta = " · ".join(v for k, v in p["specs"] if k in ("Volume", "Álcool"))
    return (f'        <a class="bebida-card" href="produto-{p["slug"]}.html"><div class="bebida-card__bottle">'
            f'<img src="imagens/produto-{p["slug"]}.png" alt="{escape(p["nome"])}" loading="lazy" /></div>'
            f'<p class="bebida-card__line">{escape(" · ".join(p["linha"].replace("Linha ", "").split(" · ")[:2]))}</p>'
            f'<h3 class="bebida-card__name">{escape(p["nome"])}</h3>'
            f'<p class="bebida-card__meta">{escape(meta)}</p>'
            f'<span class="bebida-card__cta">Conheça{ARROW_SVG}</span></a>')


def main_html(i, p, wa_svg):
    premios = ""
    if p["premios"]:
        spans = "\n".join(f'            <span class="produto__prize">\n              {MEDAL_SVG}\n'
                          f'              {escape(t)}\n            </span>' for t in p["premios"])
        premios = f'          <div class="produto__prizes">\n{spans}\n          </div>\n'
    specs = "\n".join(f'            <div class="produto__spec"><span class="produto__spec-key">{k}</span>'
                      f'<span class="produto__spec-val">{escape(v)}</span></div>' for k, v in p["specs"])
    wa = quote(f"Olá! Tenho interesse no {p['nome']}.")
    related = [PRODUTOS[(i + k) % len(PRODUTOS)] for k in (1, 2, 3)]
    rel = "\n".join(card(r) for r in related)
    return f'''  <main id="produto-page">
    <section class="produto" aria-labelledby="produto-name">
      <div class="produto__atmosphere" aria-hidden="true">
        <div class="produto__glow"></div>
        <div class="produto__vignette"></div>
      </div>
      <div class="produto__wrap">
        <div class="produto__stage">
          <div class="produto__halo" aria-hidden="true"></div>
          <img class="produto__bottle" src="imagens/produto-{p["slug"]}.png" alt="Garrafa {escape(p["nome"])}" />
          <div class="produto__rail" aria-hidden="true">
            <span>Içara · SC</span>
            <span class="produto__rail-bar"></span>
            <span>{p["rail"]}</span>
          </div>
        </div>
        <div class="produto__info">
          <a class="produto__back" href="nossas-bebidas.html">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7M19 12H5"/></svg>
            Voltar ao catálogo
          </a>
          <p class="produto__line">{escape(p["linha"])}</p>
          <h1 class="produto__name" id="produto-name">
            {p["titulo"]}
          </h1>
{premios}          <p class="produto__lede">
            {escape(p["lede"])}
          </p>
          <p class="produto__notes">
            {escape(p["notas"])}
          </p>
          <div class="produto__specs">
{specs}
          </div>
          <div class="produto__pairings">
            <span class="produto__pairings-key">Harmoniza com</span>
            <p class="produto__pairings-list">
              {escape(p["harmoniza"])}
            </p>
          </div>
          <div class="produto__ctas">
            <a class="produto__cta produto__cta--whats" href="https://api.whatsapp.com/send?phone=5548999546255&text={wa}" target="_blank" rel="noopener noreferrer">
              {wa_svg}
              Pedir pelo WhatsApp
            </a>
            <a class="produto__cta produto__cta--ghost" href="personalize.html">
              Personalizar este rótulo
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- Pull quote -->
    <section class="quote-plate" aria-label="Citação">
      <div class="quote-plate__atmosphere" aria-hidden="true"><div class="quote-plate__glow"></div></div>
      <div class="quote-plate__inner">
        <span class="quote-plate__marker" aria-hidden="true"></span>
        <blockquote class="quote-plate__text">
          <em>“</em>Os Vales da Uva Goethe formam a única região no mundo a
          produzir vinhos com a uva Goethe sob Indicação Geográfica certificada.<em>”</em>
        </blockquote>
        <p class="quote-plate__cite">D.O. Vales da Uva Goethe<span class="quote-plate__cite-role">Içara · Santa Catarina · 2025</span></p>
      </div>
    </section>

    <!-- Other bottles teaser -->
    <section class="bebidas-grid" aria-labelledby="produto-related">
      <div class="bebidas-grid__atmosphere" aria-hidden="true"><div class="bebidas-grid__glow"></div></div>
      <header class="bebidas-grid__head">
        <p class="page-head__eyebrow"><span aria-hidden="true"></span>Você também pode amar<span aria-hidden="true"></span></p>
        <h2 class="page-head__title" id="produto-related" style="font-size: clamp(32px,4.4vw,52px);">
          <em>Outros</em> rótulos da casa
        </h2>
      </header>
      <div class="bebidas-grid__wrap bebidas-grid__wrap--related">
{rel}
      </div>
    </section>
  </main>'''


def build():
    tpl = io.open(TEMPLATE, encoding="utf-8").read()
    wa_svg = re.search(r'<a class="produto__cta produto__cta--whats"[^>]*>\s*(<svg.*?</svg>)', tpl, re.S).group(1)
    for i, p in enumerate(PRODUTOS):
        sufixo = " · ".join(t.split(" · ")[0] for t in p["premios"][:1])
        titulo = f'{p["nome"]}{" · " + sufixo if sufixo else ""} · Vinhos Quarezemin'
        html = re.sub(r"<title>.*?</title>", f"<title>{escape(titulo)}</title>", tpl, count=1)
        html = re.sub(r'<meta name="description" content=".*?" />',
                      f'<meta name="description" content="{escape(p["desc"])}" />', html, count=1)
        html = re.sub(r'  <main id="produto-page">.*?</main>', lambda m: main_html(i, p, wa_svg), html,
                      count=1, flags=re.S)
        out = ROOT / f'produto-{p["slug"]}.html'
        io.open(out, "w", encoding="utf-8", newline="\n").write(html)
        print("ok", out.name)


if __name__ == "__main__":
    build()
