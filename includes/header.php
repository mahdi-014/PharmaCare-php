<?php
$active = match($page) {
    'medicine_form' => 'medicines',
    'invoice' => 'sales',
    'sale_form' => 'sale_form',
    'order_detail' => (is_customer() ? 'my_orders' : 'orders'),
    default => $page
};
$heading = page_title($page);
$subtitles = [
 'dashboard' => 'Current pharmacy stock and sales overview',
 'orders' => 'Fulfill customer orders, review prescriptions, and manage dispatch queue',
 'order_detail' => 'Review order items, verify doctor prescriptions, and manage status transitions',
 'my_orders' => 'Track your active orders and review previous medication orders',
 'medicines' => 'Search stock, prices, batch and expiry information',
 'medicine_form' => 'Enter medicine, stock, price and expiry details',
 'sale_form' => 'Select medicines and generate an invoice',
 'sales' => 'Review completed sales and print invoices',
 'invoice' => 'Printable receipt for the completed sale',
 'categories' => 'Organize medicines into searchable groups',
 'suppliers' => 'Manage supplier and contact information',
 'users' => 'Create staff accounts and control access',
 'catalog' => 'Browse, search, and order available medicines online',
 'cart' => 'Review selected medicines and adjust quantities',
 'checkout' => 'Provide delivery address and confirm your order',
 'order_confirmation' => 'Order placed successfully and queued for dispensary review',
 'profile' => 'Manage your customer profile and delivery address',
 'register' => 'Create a customer account to order medicines online'
];

$cartCount = cart_get_count();

if (!user()) {
    $links = [
        ['catalog', 'capsule-pill', 'Browse Medicines', 'catalog'],
    ];
    $brandUrl = url('catalog');
    $brandSubtitle = 'Online Storefront';
} elseif (is_customer()) {
    $cartLabel = 'Shopping Cart' . ($cartCount > 0 ? ' (' . $cartCount . ')' : '');
    $links = [
        ['catalog', 'capsule-pill', 'Browse Medicines', 'catalog'],
        ['cart', 'cart3', $cartLabel, 'cart'],
        ['my_orders', 'bag-check-fill', 'My Orders', 'my_orders'],
        ['profile', 'person-circle', 'My Profile', 'profile'],
    ];
    $brandUrl = url('catalog');
    $brandSubtitle = 'Online Storefront';
} else {
    $pendingOrdersCount = 0;
    try {
        $pendingOrdersCount = (int)scalar("SELECT COUNT(*) FROM orders WHERE order_status IN ('PENDING', 'CONFIRMED', 'PROCESSING')");
    } catch (Throwable $e) {}
    $ordersBadge = $pendingOrdersCount > 0 ? ' (' . $pendingOrdersCount . ')' : '';

    $links = [
        ['dashboard','grid-1x2-fill','Dashboard','dashboard'],
        ['orders','box-seam-fill','Orders Queue' . $ordersBadge,'orders'],
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
    $brandUrl = url('dashboard');
    $brandSubtitle = 'Management System';
}
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="PharmaCare Pharmacy Management System">
<meta name="csrf-token" content="<?= e(csrf()) ?>">
<title><?=e($heading)?> | PharmaCare</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css"></head><body>
<aside class="app-sidebar">
 <a class="brand" href="<?=e($brandUrl)?>"><span class="brand-mark"><i class="bi bi-capsule-pill"></i></span><span><strong>PharmaCare</strong><small><?=e($brandSubtitle)?></small></span></a>
 <nav class="sidebar-nav">
 <?php foreach ($links as [$dest, $icon, $label, $selected]): ?>
 <?php if ($dest === 'section'): ?><div class="nav-section"><?=e($label)?></div>
 <?php else: ?><a class="<?=($active === $selected ? 'active' : '')?>" href="<?=e(url($dest))?>"><i class="bi bi-<?=e($icon)?>"></i><span><?=e($label)?></span></a><?php endif; ?>
 <?php endforeach; ?>
 </nav>
 <?php if (user()): ?>
  <div class="sidebar-footer">
   <div class="small text-white-50">Signed in as</div>
   <div class="fw-semibold text-white text-truncate"><?=e(user()['username'])?> <small>(<?=e(user()['role'])?>)</small></div>
   <form method="post" action="actions.php"><input type="hidden" name="action" value="logout"><?=csrf_field()?><button class="btn btn-sm btn-outline-light w-100" type="submit"><i class="bi bi-box-arrow-left me-1"></i><span>Logout</span></button></form>
  </div>
 <?php else: ?>
  <div class="sidebar-footer">
   <div class="small text-white-50">Customer Access</div>
   <div class="d-flex flex-column gap-2 mt-2">
    <a href="index.php?page=login" class="btn btn-sm btn-success w-100"><i class="bi bi-box-arrow-in-right me-1"></i>Sign In</a>
    <a href="index.php?page=register" class="btn btn-sm btn-outline-light w-100"><i class="bi bi-person-plus me-1"></i>Register</a>
   </div>
  </div>
 <?php endif; ?>
</aside>
<div class="app-main">
 <header class="app-topbar">
  <div class="d-flex align-items-center gap-3">
   <button type="button" class="btn btn-light d-sm-none" id="mobileMenuBtn" aria-label="Open menu"><i class="bi bi-list fs-5"></i></button>
   <div><h1><?=e($heading)?></h1><p><?=e($subtitles[$page]??'PharmaCare Management System')?></p></div>
  </div>
  <?php if (user()): ?>
   <div class="d-flex align-items-center gap-2">
    <?php if (is_customer()): ?>
     <a href="<?= e(url('cart')) ?>" class="btn btn-outline-success position-relative me-2" title="Shopping Cart">
      <i class="bi bi-cart3"></i>
      <span class="d-none d-md-inline ms-1">Cart</span>
      <span id="topbarCartBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger <?= $cartCount > 0 ? '' : 'd-none' ?>" style="font-size: 11px;">
       <?= $cartCount ?>
      </span>
     </a>
    <?php endif; ?>
    <div class="topbar-user"><span class="avatar"><i class="bi bi-person-fill"></i></span><span><strong><?=e(user()['full_name'])?></strong><small><?=e(user()['role'])?> account</small></span></div>
   </div>
  <?php else: ?>
   <div class="d-flex align-items-center gap-2">
    <a href="index.php?page=login" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-in-right me-1"></i>Sign In</a>
    <a href="index.php?page=register" class="btn btn-sm btn-success"><i class="bi bi-person-plus me-1"></i>Register</a>
   </div>
  <?php endif; ?>
 </header>
 <main class="content">
 <?php show_flashes(); ?>