<?php
declare(strict_types=1);

/** Daftar rute aplikasi (PRD bagian 8). */
return static function (Router $router): void {
    $router->get('/', 'page_home');
    $router->get('/privasi', 'page_privacy');
    $router->get('/penyusun', 'page_penyusun');
    $router->get('/lapor', 'page_report_form');
    $router->post('/lapor', 'page_report_submit');
    $router->get('/lapor/sukses', 'page_report_success');
    $router->get('/status', 'page_status_form');
    $router->post('/status', 'page_status_lookup');

    $router->get('/bk/login', 'page_bk_login_form');
    $router->post('/bk/login', 'page_bk_login_submit');
    $router->post('/bk/logout', 'page_bk_logout');

    $router->get('/akun', 'page_account_form');
    $router->post('/akun', 'page_account_submit');
    $router->post('/akun/username', 'page_account_username');
    $router->post('/akun/password', 'page_account_password');

    $router->get('/bk/laporan', 'page_bk_list');
    $router->get('/bk/laporan/{id}', 'page_bk_detail');
    $router->post('/bk/laporan/{id}/balas', 'page_bk_reply');
    $router->post('/bk/laporan/{id}/status', 'page_bk_update_status');
    $router->post('/bk/laporan/{id}/urgensi', 'page_bk_update_urgency');
    $router->post('/bk/laporan/{id}/catatan', 'page_bk_add_note');
    $router->get('/bk/laporan/{id}/hapus', 'page_bk_delete_confirm');
    $router->post('/bk/laporan/{id}/hapus', 'page_bk_delete_execute');
    $router->get('/bk/lampiran/{id}', 'page_bk_attachment');
    $router->get('/bk/rekap', 'page_bk_rekap');

    $router->get('/admin/akun', 'page_admin_accounts');
    $router->post('/admin/akun/buat', 'page_admin_account_create');
    $router->post('/admin/akun/{id}/status', 'page_admin_account_toggle');
    $router->post('/admin/akun/{id}/password', 'page_admin_account_password');

    $router->get('/admin/kategori', 'page_admin_categories');
    $router->post('/admin/kategori/tambah', 'page_admin_category_create');
    $router->post('/admin/kategori/{id}/ubah', 'page_admin_category_update');
    $router->post('/admin/kategori/{id}/status', 'page_admin_category_toggle');

    $router->get('/admin/statistik', 'page_admin_stats');
    $router->get('/admin/backup', 'page_admin_backup');
    $router->post('/admin/backup/unduh', 'page_admin_backup_download');
    $router->get('/admin/reset', 'page_admin_reset_form');
    $router->post('/admin/reset', 'page_admin_reset_execute');
};
