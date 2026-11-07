<?php
$active = match($page) { 'medicine_form'=>'medicines','invoice'=>'sales','sale_form'=>'sale_form',default=>$page };
$heading = page_title($page);
$subtitles = [
 'dashboard' => 'Current pharmacy stock and sales overview',
 'medicines' => 'Search stock, prices, batch and expiry information',
 'medicine_form' => 'Enter medicine, stock, price and expiry details',
 'sale_form' => 'Select medicines and generate an invoice',
 'sales' => 'Review completed sales and print invoices',
 'invoice' => 'Printable receipt for the completed sale',
 'categories' => 'Organize medicines into searchable groups',
 'suppliers' => 'Manage supplier and contact information',
 'users' => 'Create staff accounts and control access',
];
$links = [
 ['dashboard','grid-1x2-fill','Dashboard','dashboard'],
 ['medicines','capsule','Medicines','medicines'],
 ['sale_form','cart-plus-fill','New Sale','sale_form'],
 ['sales','receipt','Sales History','sales'],
];
if (is_admin()) {
 $links[] = ['section', '', 'Administration', ''];
 $links[] = ['categories', 'tags-fill','Categories','categories'];
 $links[] = ['suppliers','truck','Suppliers','suppliers'];
 $links[] = ['users','people-fill','Users','users'];
}
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="PharmaCare Pharmacy Management System">
<title><?=e($heading)?> | PharmaCare</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css"></head><body>
<aside class="app-sidebar">
 <a class="brand" href="<?=e(url('dashboard'))?>"><span class="brand-mark"><i class="bi bi-capsule-pill"></i></span><span><strong>PharmaCare</strong><small>Management System</small></span></a>
 <nav class="sidebar-nav">
 <?php foreach ($links as [$dest, $icon, $label, $selected]): ?>
 <?php if ($dest === 'section'): ?><div class="nav-section"><?=e($label)?></div>
 <?php else: ?><a class="<?=($active === $selected ? 'active' : '')?>" href="<?=e(url($dest))?>"><i class="bi bi-<?=e($icon)?>"></i><span><?=e($label)?></span></a><?php endif; ?>
 <?php endforeach; ?>
 </nav>
 <div class="sidebar-footer"><div class="small text-white-50">Signed in as</div><div class="fw-semibold text-white text-truncate"><?=e(user()['username'])?> <small>(<?=e(user()['role'])?>)</small></div>
 <form method="post" action="actions.php"><input type="hidden" name="action" value="logout"><?=csrf_field()?><button class="btn btn-sm btn-outline-light w-100" type="submit"><i class="bi bi-box-arrow-left me-1"></i><span>Logout</span></button></form></div>
</aside>
<div class="app-main">
 <header class="app-topbar"><div class="d-flex align-items-center gap-3"><button type="button" class="btn btn-light d-sm-none" id="mobileMenuBtn" aria-label="Open menu"><i class="bi bi-list fs-5"></i></button><div><h1><?=e($heading)?></h1><p><?=e($subtitles[$page]??'PharmaCare Management System')?></p></div></div><div class="topbar-user"><span class="avatar"><i class="bi bi-person-fill"></i></span><span><strong><?=e(user()['full_name'])?></strong><small><?=e(user()['role'])?> account</small></span></div></header>
 <main class="content">
 <?php show_flashes(); ?>
