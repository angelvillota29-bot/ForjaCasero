<?php
require_once __DIR__ . '/telegram.php';

function gmailListLabels(string $accessToken): array {
    $result = httpGetJsonAuth('https://gmail.googleapis.com/gmail/v1/users/me/labels', $accessToken);
    return $result['labels'] ?? [];
}

// Query estilo Gmail, ej: "in:inbox -label:Angel -label:Ana"
function gmailListMessageIds(string $accessToken, string $query, int $maxResults = 15): array {
    $params = http_build_query(['q' => $query, 'maxResults' => $maxResults]);
    $result = httpGetJsonAuth('https://gmail.googleapis.com/gmail/v1/users/me/messages?' . $params, $accessToken);
    return array_map(fn($m) => $m['id'], $result['messages'] ?? []);
}

function gmailGetMessageMeta(string $accessToken, string $id): array {
    $params = http_build_query([
        'format' => 'metadata',
        'metadataHeaders' => 'From',
    ]) . '&metadataHeaders=Subject';
    $result = httpGetJsonAuth("https://gmail.googleapis.com/gmail/v1/users/me/messages/{$id}?{$params}", $accessToken);
    $headers = $result['payload']['headers'] ?? [];
    $from = '';
    $subject = '';
    foreach ($headers as $h) {
        if (strcasecmp($h['name'], 'From') === 0) $from = $h['value'];
        if (strcasecmp($h['name'], 'Subject') === 0) $subject = $h['value'];
    }
    return [
        'id' => $id,
        'from' => $from,
        'subject' => $subject,
        'snippet' => $result['snippet'] ?? '',
    ];
}

function gmailAddLabel(string $accessToken, string $messageId, string $labelId, bool $archive = true): array {
    $body = ['addLabelIds' => [$labelId]];
    if ($archive) {
        $body['removeLabelIds'] = ['INBOX'];
    }
    $result = httpPostJson("https://gmail.googleapis.com/gmail/v1/users/me/messages/{$messageId}/modify", $body, ['Authorization: Bearer ' . $accessToken]);
    return $result['body'];
}
