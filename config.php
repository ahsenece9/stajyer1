<?php
declare(strict_types=1);

/**
 * Uygulama modu:
 *   'live' → gerçek veritabanı (stajyer_takip) — canlı kullanım.
 *   'demo' → örnek/sahte veritabanı (stajyer_takip_demo) — test ve tanıtım.
 * Modu değiştirmek için sadece aşağıdaki satırı 'live' veya 'demo' yapın.
 */
const APP_MODE = 'live';

/**
 * Veritabanı ayarları — sunucunuza göre düzenleyin.
 */
const DB_HOST = 'localhost';
const DB_NAME      = 'stajyer_takip';       // Canlı veritabanı
const DB_NAME_DEMO = 'stajyer_takip_demo';  // Demo (örnek veri) veritabanı
const DB_USER = 'root';
const DB_PASS = '';

/** Fotoğraf yükleme ayarları */
const UPLOAD_DIR      = __DIR__ . '/uploads';
const UPLOAD_MAX_SIZE = 4 * 1024 * 1024; // 4 MB
