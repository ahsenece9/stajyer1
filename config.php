<?php
declare(strict_types=1);

/**
 * Veritabanı ayarları — sunucunuza göre düzenleyin.
 */
const DB_HOST = 'localhost';
const DB_NAME = 'stajyer_takip';
const DB_USER = 'root';
const DB_PASS = '';

/** Fotoğraf yükleme ayarları */
const UPLOAD_DIR      = __DIR__ . '/uploads';
const UPLOAD_MAX_SIZE = 4 * 1024 * 1024; // 4 MB
