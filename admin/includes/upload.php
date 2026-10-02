<?php
// Upload seguro das fotos do consultório.

const TIPOS_IMAGEM = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

/**
 * Valida e salva um arquivo de imagem vindo de $_FILES.
 * Retorna o nome do arquivo salvo, null se nenhum arquivo foi enviado,
 * ou lança RuntimeException com a mensagem de erro para o usuário.
 */
function salvarImagem(?array $arq): ?string
{
    if (!$arq || $arq['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($arq['error'] === UPLOAD_ERR_INI_SIZE || $arq['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('A imagem é grande demais (máximo 5 MB).');
    }
    if ($arq['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($arq['tmp_name'])) {
        throw new RuntimeException('Falha no envio da imagem. Tente novamente.');
    }
    if ($arq['size'] > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('A imagem é grande demais (máximo 5 MB).');
    }

    // Confere o tipo real pelo conteúdo, não pela extensão enviada
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($arq['tmp_name']);
    if (!isset(TIPOS_IMAGEM[$mime]) || @getimagesize($arq['tmp_name']) === false) {
        throw new RuntimeException('Formato inválido. Envie JPG, PNG ou WEBP.');
    }

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
        throw new RuntimeException('Pasta de upload indisponível.');
    }

    $nome = bin2hex(random_bytes(12)) . '.' . TIPOS_IMAGEM[$mime];
    if (!move_uploaded_file($arq['tmp_name'], UPLOAD_DIR . $nome)) {
        throw new RuntimeException('Não foi possível salvar a imagem.');
    }
    return $nome;
}

function removerImagem(?string $nome): void
{
    // basename impede apagar arquivos fora da pasta de upload
    if ($nome && is_file(UPLOAD_DIR . basename($nome))) {
        unlink(UPLOAD_DIR . basename($nome));
    }
}
