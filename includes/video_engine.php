<?php
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/telegram.php';
// Motor de generación de videos publicitarios: toma screen-recordings crudos
// que sube el usuario, les agrega intro/outro con texto (gancho + CTA),
// marca de agua del logo, música de fondo y subtítulos automáticos
// (transcritos con Whisper), y entrega un único MP4 vertical listo para
// publicar. Todo corre localmente con ffmpeg, sin servicios de pago externos.

const VIDEO_W = 1080;
const VIDEO_H = 1920;
const VIDEO_FPS = 30;
const VIDEO_FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

function videoTmpDir(string $projectId): string {
    $dir = mediaDir() . '/tmp/' . $projectId;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

// Ejecuta ffmpeg/ffprobe por argv (nunca por string de shell) para no
// arrastrar problemas de escapado/inyección con texto que viene del usuario.
function runMediaCommand(array $args, int $timeoutSeconds = 300): array {
    $fullArgs = array_merge(['timeout', (string) $timeoutSeconds], $args);
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open($fullArgs, $descriptors, $pipes);
    if (!is_resource($proc)) {
        return ['ok' => false, 'output' => 'No se pudo iniciar el proceso'];
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    return ['ok' => $code === 0, 'output' => trim($stderr . "\n" . $stdout)];
}

function ffprobeDuration(string $path): float {
    $result = runMediaCommand([
        'ffprobe', '-v', 'error', '-show_entries', 'format=duration',
        '-of', 'default=noprint_wrappers=1:nokey=1', $path,
    ], 30);
    return $result['ok'] ? (float) trim($result['output']) : 0.0;
}

function clipHasAudio(string $path): bool {
    $result = runMediaCommand([
        'ffprobe', '-v', 'error', '-select_streams', 'a', '-show_entries', 'stream=index',
        '-of', 'csv=p=0', $path,
    ], 30);
    return $result['ok'] && trim($result['output']) !== '';
}

function drawtextEscape(string $text): string {
    $text = str_replace('\\', '\\\\', $text);
    $text = str_replace(':', '\\:', $text);
    $text = str_replace("'", "\u{2019}", $text); // comilla curva: evita cerrar el quoting del filtro
    return $text;
}

function wrapForCard(string $text, int $width = 26): string {
    return wordwrap($text, $width, "\n", true);
}

// Tarjeta de color sólido con texto centrado (se usa para intro/outro).
function buildTextCard(string $text, float $duration, string $outputPath, string $bgColor = '0x111C2E'): array {
    $wrapped = drawtextEscape(wrapForCard($text));
    $drawtext = 'drawtext=fontfile=' . VIDEO_FONT . ":text='{$wrapped}':fontcolor=white:fontsize=58:line_spacing=16:x=(w-text_w)/2:y=(h-text_h)/2:expansion=none";
    $result = runMediaCommand([
        'ffmpeg', '-y',
        '-f', 'lavfi', '-i', "color=c={$bgColor}:s=" . VIDEO_W . 'x' . VIDEO_H . ":d={$duration}:r=" . VIDEO_FPS,
        '-f', 'lavfi', '-i', 'anullsrc=r=44100:cl=stereo',
        '-shortest',
        '-vf', $drawtext,
        '-c:v', 'libx264', '-preset', 'veryfast', '-pix_fmt', 'yuv420p',
        '-c:a', 'aac', '-ar', '44100', '-ac', '2',
        '-t', (string) $duration,
        $outputPath,
    ], 60);
    return $result;
}

// Genera un .srt a partir de un clip narrado, usando Whisper en modo
// verbose_json para obtener tiempos por segmento.
function transcribeToSrt(string $apiKey, string $clipPath, string $srtPath): bool {
    $audioPath = $srtPath . '.wav';
    $extract = runMediaCommand(['ffmpeg', '-y', '-i', $clipPath, '-vn', '-ar', '16000', '-ac', '1', $audioPath], 60);
    if (!$extract['ok'] || !file_exists($audioPath)) {
        return false;
    }
    $bytes = file_get_contents($audioPath);
    @unlink($audioPath);
    if ($bytes === false) {
        return false;
    }
    $result = httpPostMultipart(
        'https://api.openai.com/v1/audio/transcriptions',
        ['model' => 'whisper-1', 'response_format' => 'verbose_json'],
        'file', $bytes, 'audio.wav', 'audio/wav',
        ['Authorization: Bearer ' . $apiKey], 60
    );
    $segments = $result['segments'] ?? null;
    if (!is_array($segments) || count($segments) === 0) {
        return false;
    }
    $lines = [];
    $i = 1;
    foreach ($segments as $seg) {
        $start = srtTimestamp((float) ($seg['start'] ?? 0));
        $end = srtTimestamp((float) ($seg['end'] ?? 0));
        $text = trim((string) ($seg['text'] ?? ''));
        if ($text === '') continue;
        $lines[] = (string) $i;
        $lines[] = "{$start} --> {$end}";
        $lines[] = $text;
        $lines[] = '';
        $i++;
    }
    if (empty($lines)) {
        return false;
    }
    return file_put_contents($srtPath, implode("\n", $lines)) !== false;
}

function srtTimestamp(float $seconds): string {
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = floor($seconds) % 60;
    $ms = round(($seconds - floor($seconds)) * 1000);
    return sprintf('%02d:%02d:%02d,%03d', $h, $m, $s, $ms);
}

// Escala/rellena un clip crudo al lienzo vertical estándar y opcionalmente
// le quema subtítulos ya generados.
function normalizeClip(string $inputPath, string $outputPath, ?string $srtPath = null): array {
    $vf = 'scale=' . VIDEO_W . ':' . VIDEO_H . ':force_original_aspect_ratio=decrease,pad=' . VIDEO_W . ':' . VIDEO_H . ':(ow-iw)/2:(oh-ih)/2:color=0x111C2E,setsar=1,fps=' . VIDEO_FPS;
    if ($srtPath !== null && file_exists($srtPath)) {
        $escapedSrt = str_replace([':', "'"], ['\\:', "\\'"], $srtPath);
        $vf .= ",subtitles='{$escapedSrt}':force_style='FontName=DejaVu Sans,FontSize=20,PrimaryColour=&H00FFFFFF,OutlineColour=&H00000000,BorderStyle=3,MarginV=80'";
    }
    $hasAudio = clipHasAudio($inputPath);
    $args = ['ffmpeg', '-y', '-i', $inputPath];
    if (!$hasAudio) {
        $args = array_merge($args, ['-f', 'lavfi', '-i', 'anullsrc=r=44100:cl=stereo', '-shortest']);
    }
    $args = array_merge($args, [
        '-vf', $vf,
        '-af', 'aresample=44100',
        '-c:v', 'libx264', '-preset', 'veryfast', '-pix_fmt', 'yuv420p',
        '-c:a', 'aac', '-ar', '44100', '-ac', '2',
        $outputPath,
    ]);
    return runMediaCommand($args, 240);
}

function concatSegments(array $segmentPaths, string $outputPath, string $listFilePath): array {
    $lines = array_map(function ($path) {
        $escaped = str_replace("'", "'\\''", $path);
        return "file '{$escaped}'";
    }, $segmentPaths);
    file_put_contents($listFilePath, implode("\n", $lines));
    return runMediaCommand(['ffmpeg', '-y', '-f', 'concat', '-safe', '0', '-i', $listFilePath, '-c', 'copy', $outputPath], 120);
}

function applyWatermark(string $inputPath, string $logoPath, string $outputPath): array {
    return runMediaCommand([
        'ffmpeg', '-y', '-i', $inputPath, '-i', $logoPath,
        '-filter_complex', '[1:v]scale=170:-1[wm];[0:v][wm]overlay=W-w-22:H-h-40:format=auto',
        '-c:a', 'copy', '-c:v', 'libx264', '-preset', 'veryfast', '-pix_fmt', 'yuv420p',
        $outputPath,
    ], 180);
}

function mixMusic(string $inputPath, string $musicPath, string $outputPath, float $duration): array {
    return runMediaCommand([
        'ffmpeg', '-y', '-i', $inputPath, '-stream_loop', '-1', '-i', $musicPath,
        '-filter_complex', "[1:a]volume=0.16,atrim=0:{$duration},asetpts=PTS-STARTPTS[bgm];[0:a][bgm]amix=inputs=2:duration=first:dropout_transition=2[aout]",
        '-map', '0:v', '-map', '[aout]',
        '-c:v', 'copy', '-c:a', 'aac', '-ar', '44100',
        $outputPath,
    ], 180);
}

// Orquesta el render completo. $clipPaths ya vienen en el orden final.
function renderVideoProject(array $project, array $clipPaths, ?string $logoPath, ?string $musicPath, ?string $openAiKey, string $finalOutputPath): array {
    $projectId = $project['id'];
    $tmp = videoTmpDir($projectId);
    cleanupTmpDir($tmp); // por si quedó basura de un render anterior fallido
    $tmp = videoTmpDir($projectId);
    $segments = [];

    if (!empty($project['hook'])) {
        $intro = "{$tmp}/00_intro.mp4";
        $r = buildTextCard($project['hook'], 2.6, $intro);
        if (!$r['ok']) return ['ok' => false, 'error' => 'Error generando intro: ' . $r['output']];
        $segments[] = $intro;
    }

    $index = 1;
    foreach ($clipPaths as $clipPath) {
        $srtPath = null;
        if (!empty($project['subtitlesEnabled']) && $openAiKey && clipHasAudio($clipPath)) {
            $candidateSrt = "{$tmp}/sub_{$index}.srt";
            if (transcribeToSrt($openAiKey, $clipPath, $candidateSrt)) {
                $srtPath = $candidateSrt;
            }
        }
        $normalized = "{$tmp}/clip_{$index}.mp4";
        $r = normalizeClip($clipPath, $normalized, $srtPath);
        if (!$r['ok']) return ['ok' => false, 'error' => "Error procesando clip {$index}: " . $r['output']];
        $segments[] = $normalized;
        $index++;
    }

    if (!empty($project['cta'])) {
        $outro = "{$tmp}/99_outro.mp4";
        $r = buildTextCard($project['cta'], 3.0, $outro, '0x0B3D2E');
        if (!$r['ok']) return ['ok' => false, 'error' => 'Error generando outro: ' . $r['output']];
        $segments[] = $outro;
    }

    if (empty($segments)) {
        return ['ok' => false, 'error' => 'No hay clips ni tarjetas para armar el video'];
    }

    $concatenated = "{$tmp}/concat.mp4";
    $r = concatSegments($segments, $concatenated, "{$tmp}/list.txt");
    if (!$r['ok']) return ['ok' => false, 'error' => 'Error uniendo segmentos: ' . $r['output']];

    $current = $concatenated;
    if ($logoPath && file_exists($logoPath)) {
        $watermarked = "{$tmp}/watermarked.mp4";
        $r = applyWatermark($current, $logoPath, $watermarked);
        if (!$r['ok']) return ['ok' => false, 'error' => 'Error con marca de agua: ' . $r['output']];
        $current = $watermarked;
    }

    if (!empty($project['musicEnabled']) && $musicPath && file_exists($musicPath)) {
        $duration = ffprobeDuration($current);
        if ($duration > 0) {
            $withMusic = "{$tmp}/withmusic.mp4";
            $r = mixMusic($current, $musicPath, $withMusic, $duration);
            if (!$r['ok']) return ['ok' => false, 'error' => 'Error mezclando música: ' . $r['output']];
            $current = $withMusic;
        }
    }

    if (!@rename($current, $finalOutputPath)) {
        if (!@copy($current, $finalOutputPath)) {
            return ['ok' => false, 'error' => 'No se pudo guardar el archivo final'];
        }
    }

    cleanupTmpDir($tmp);
    return ['ok' => true];
}

function cleanupTmpDir(string $dir): void {
    if (!is_dir($dir)) return;
    $files = glob($dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) @unlink($file);
    }
    @rmdir($dir);
}
