<?php
declare(strict_types=1);

/**
 * Sürüm ve değişiklik günlüğü (changelog).
 *
 * Kural: En küçük değişiklikte bile (bir virgül dahi) sürüm artırılır ve
 * buraya kısa bir açıklamayla yeni bir satır eklenir.
 *
 * Sürümleme: SÜRÜM.ÖZELLİK.DÜZELTME
 *   - Büyük/yapısal değişiklik  → ilk sayı
 *   - Yeni özellik              → ikinci sayı
 *   - Küçük düzeltme/rötuş      → üçüncü sayı
 *
 * APP_CHANGELOG en yeni sürüm en üstte olacak şekilde sıralanır.
 * APP_VERSION otomatik olarak listenin ilk (en yeni) sürümüdür.
 */

const APP_CHANGELOG = [
    ['1.2.0', '2026-07-07', 'Aynı TC ile farklı dönemlerde tekrar staj kaydı yapılabiliyor; stajyer profilinde geçmiş stajlar ve değerlendirmeleri listeleniyor. Aynı dönemde çift kayıt engellendi.'],
    ['1.1.1', '2026-07-07', 'Kapsamlı loglama: başvuru, kayıt talebi, düzenleme, kontenjan ve onay işlemleri dahil tüm işlemler kullanıcı ve IP adresiyle kaydediliyor.'],
    ['1.1.0', '2026-07-07', 'Kayıt onay sistemi: yeni kayıtlar doğrudan sisteme giremiyor; sistem yöneticisi onayından ve birim atamasından geçmesi gerekiyor.'],
    ['1.0.2', '2026-07-07', 'Sürüm sistemi ve sürüm sayfası eklendi.'],
    ['1.0.1', '2026-07-07', 'Sayfa altbilgisi (footer) metni güncellendi.'],
    ['1.0.0', '2026-07-06', 'İlk sürüm: stajyer takibi, dijital yoklama, dönem ve kontenjan yönetimi, başvuru modülü, kullanıcı yönetimi, log sistemi ve çoklu tema desteği.'],
];

/** Uygulamanın güncel sürümü (changelog'daki en yeni sürüm). */
const APP_VERSION = APP_CHANGELOG[0][0];
