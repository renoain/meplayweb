<?php
function isAllowedImageFile($filename) {
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($file_extension, $allowed_extensions);
}

function isAllowedAudioFile($filename) {
    $allowed_extensions = ['mp3', 'wav', 'ogg', 'm4a'];
    $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($file_extension, $allowed_extensions);
}

function validateFileSize($file, $max_size_mb = 5) {
    $max_size = $max_size_mb * 1024 * 1024;  
    return $file['size'] <= $max_size;
}

function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}
?>