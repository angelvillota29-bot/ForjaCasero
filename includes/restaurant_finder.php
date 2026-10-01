<?php
require_once __DIR__ . '/telegram.php';

// Busca restaurantes en una ciudad/zona y detecta cuáles NO parecen tener un
// sitio web propio -- con búsqueda web normal (el HTML de resultados de
// DuckDuckGo), sin ninguna API de pago tipo Google Places.
//
// Es un método de "mejor esfuerzo": no hay forma 100% confiable de saber si
// un negocio tiene o no tiene sitio propio sin pagar por un directorio/API
// (Google Places, etc.). Esto reconoce patrones (si el negocio solo aparece
// en Facebook/Instagram/directorios, se marca como "sin sitio propio") y
// puede fallar en ambas direcciones: puede marcar un negocio que sí tiene
// web (si Google/DDG no la indexó bien) o pasar por alto uno que no tiene
// (si no apareció en la búsqueda). También puede dejar de funcionar si
// DuckDuckGo cambia el formato de su página de resultados.

const RF_DOMINIOS_NO_PROPIOS = [
    'facebook.com', 'instagram.com', 'tiktok.com', 'wa.me', 'whatsapp.com',
    'paginasamarillas.com.co', 'tripadvisor.com', 'tripadvisor.com.co',
    'google.com', 'goo.gl', 'maps.app.goo.gl', 'linktr.ee', 'twitter.com', 'x.com',
    'yelp.com', 'rappi.com', 'didi.co', 'ubereats.com', 'duckduckgo.com',
    'youtube.com', 'laguia.co', 'lanetacity.com', 'paginasblancas.com',
];

function rfEsSitioPropio(string $url): bool {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return false;
    $host = strtolower(preg_replace('/^www\./', '', $host));
    foreach (RF_DOMINIOS_NO_PROPIOS as $d) {
        if ($host === $d || str_ends_with($host, '.' . $d)) return false;
    }
    return true;
}

// Pega al HTML de resultados de DuckDuckGo (sin API key) y devuelve
// [['titulo'=>..., 'url'=>...], ...].
function rfDuckDuckGoBuscar(string $query, int $max = 10): array {
    $url = 'https://html.duckduckgo.com/html/?q=' . urlencode($query);
    $html = httpGetRaw($url);
    if ($html === null) return [];
    $resultados = [];
    if (preg_match_all('/<a[^>]+class="result__a"[^>]+href="([^"]+)"[^>]*>(.*?)<\/a>/is', $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $match) {
            if (count($resultados) >= $max) break;
            $hrefCrudo = html_entity_decode($match[1], ENT_QUOTES);
            $real = $hrefCrudo;
            // DuckDuckGo suele envolver el link real en /l/?uddg=<urlencoded>
            if (preg_match('/[?&]uddg=([^&]+)/', $hrefCrudo, $mm)) {
                $real = urldecode($mm[1]);
            }
            $titulo = trim(strip_tags(html_entity_decode($match[2], ENT_QUOTES)));
            if ($titulo === '' || $real === '') continue;
            $resultados[] = ['titulo' => $titulo, 'url' => $real];
        }
    }
    return $resultados;
}

// Paso 1: candidatos a restaurante en la ciudad (resultados de una búsqueda
// general). Paso 2: por cada nombre candidato, una búsqueda dirigida
// "<nombre> <ciudad>" para decidir si tiene sitio propio o no.
function buscarRestaurantesSinSitioWeb(string $ciudad, int $cantidad = 8): array {
    $cantidad = max(1, min($cantidad, 15));
    $candidatos = rfDuckDuckGoBuscar("restaurantes en {$ciudad}", 25);

    $nombresVistos = [];
    $sinSitio = [];
    foreach ($candidatos as $c) {
        if (count($sinSitio) >= $cantidad) break;

        // El título de un resultado de listado suele ser "Nombre - algo más"
        // o "Nombre | algo más" -- nos quedamos con la primera parte.
        $nombre = trim(preg_split('/\s[-|\x{2013}]\s/u', $c['titulo'])[0] ?? '');
        $clave = mb_strtolower($nombre);
        if ($nombre === '' || mb_strlen($nombre) < 3 || isset($nombresVistos[$clave])) continue;
        $nombresVistos[$clave] = true;

        // Si el propio resultado de la búsqueda general ya apunta a un
        // sitio propio, probablemente ese es el sitio del negocio -- no
        // calificar como "sin sitio web".
        if (rfEsSitioPropio($c['url'])) continue;

        $verificacion = rfDuckDuckGoBuscar("\"{$nombre}\" {$ciudad} restaurante", 5);
        $tieneSitioPropio = false;
        foreach ($verificacion as $v) {
            if (rfEsSitioPropio($v['url'])) { $tieneSitioPropio = true; break; }
        }
        if ($tieneSitioPropio) continue;

        $sinSitio[] = [
            'nombre' => $nombre,
            'ciudad' => $ciudad,
            'referencia' => $c['url'],
        ];
    }
    return $sinSitio;
}
