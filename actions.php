<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST required.'); }
csrf_verify();
$action = (string)($_POST['action'] ?? '');
if ($action === 'login') {
    if (user()) redirect(is_customer() ? 'catalog' : 'dashboard');
    try {
        $identifier = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $account = fetch_one('SELECT id, full_name, username, email, password, role, enabled FROM app_users WHERE username = ? OR email = ? LIMIT 1', [$identifier, $identifier]);
        if (!$account || !(int)$account['enabled'] || !password_verify($password, $account['password'])) {
            flash('error', 'Invalid username or password.');
            redirect('login');
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$account['id'];
        $_SESSION['user'] = [
            'id' => (int)$account['id'],
            'full_name' => $account['full_name'],
            'username' => $account['username'],
            'role' => $account['role']
        ];
        $_SESSION['last_activity'] = time();
        flash('success', 'Welcome back, ' . $account['full_name'] . '!');
        redirect($account['role'] === 'CUSTOMER' ? 'catalog' : 'dashboard');
    } catch (PDOException $ex) {
        flash('error', 'Database unavailable. Please check configuration.');
        redirect('login');
    }
}
if ($action === 'register') {
    if (user()) redirect(is_customer() ? 'catalog' : 'dashboard');
    try {
        $fullName = required_text('full_name', 80);
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $address = required_text('address', 255);
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            throw new DomainException('Username must contain 3–50 letters, numbers, periods, underscores or hyphens.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
            throw new DomainException('Please enter a valid email address (up to 120 characters).');
        }
        if (!preg_match('/^(?:\+?880|0)?1[3-9]\d{8}$/', $phone)) {
            throw new DomainException('Please enter a valid 11-digit mobile number (e.g., 017XXXXXXXX).');
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            throw new DomainException('Password must contain 8–72 characters.');
        }
        if ($password !== $confirmPassword) {
            throw new DomainException('Password confirmation does not match.');
        }

        if (fetch_one('SELECT id FROM app_users WHERE LOWER(username) = LOWER(?)', [$username])) {
            throw new DomainException('This username is already taken. Please choose another.');
        }
        if (fetch_one('SELECT id FROM app_users WHERE LOWER(email) = LOWER(?)', [$email])) {
            throw new DomainException('This email is already registered. Please sign in or use another email.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        execute_sql(
            'INSERT INTO app_users (full_name, username, email, phone, address, password, role, enabled) VALUES (?, ?, ?, ?, ?, ?, "CUSTOMER", 1)',
            [$fullName, $username, $email, $phone, $address, $hash]
        );

        flash('success', 'Your account has been created successfully! Please sign in with your credentials.');
        redirect('login');
    } catch (DomainException $ex) {
        flash('error', $ex->getMessage());
        redirect('register');
    } catch (PDOException $ex) {
        flash('error', 'Registration could not be completed. Please try again.');
        redirect('register');
    }
}
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
    }
    session_destroy();
    redirect('login');
}
// Cart and Order actions authorization guard
$customerActions = ['cart_add', 'cart_update', 'cart_remove', 'cart_clear', 'order_create', 'order_cancel_customer'];
if (in_array($action, $customerActions, true)) {
    if (!user()) {
        flash('error', 'Please sign in or create an account to manage your shopping cart and place orders.');
        redirect('login');
    }
    if (!is_customer()) {
        flash('error', 'Staff and Administrator accounts cannot place customer online orders. Please use POS or sign in as a customer.');
        redirect('dashboard');
    }
}

require_login();

// Staff and Admin order fulfillment actions
$staffOrAdminActions = ['order_update_status', 'prescription_review'];
if (in_array($action, $staffOrAdminActions, true)) {
    require_staff_or_admin();
}

// Sensitive actions must be authorized independently of the UI.
$adminActions = ['category_save','category_delete','supplier_save','supplier_delete','medicine_save','medicine_toggle','user_create','user_toggle'];
if (in_array($action, $adminActions, true)) require_admin();
$fallback = match($action) {
    'category_save','category_delete'=>'categories','supplier_save','supplier_delete'=>'suppliers',
    'medicine_save','medicine_toggle'=>'medicines','user_create','user_toggle'=>'users',
    'sale_create'=>'sale_form','profile_update'=>'profile',
    'cart_add'=>'catalog','cart_update','cart_remove','cart_clear'=>'cart',
    'order_create'=>'checkout',
    'order_update_status','prescription_review'=>'orders',
    'order_cancel_customer'=>'my_orders',
    default=>'dashboard'
};
try {
    switch ($action) {
        case 'profile_update':
            $userId = (int)user()['id'];
            $fullName = required_text('full_name', 80);
            $phone = trim((string)($_POST['phone'] ?? ''));
            $address = required_text('address', 255);

            if (!preg_match('/^(?:\+?880|0)?1[3-9]\d{8}$/', $phone)) {
                throw new DomainException('Please enter a valid 11-digit mobile number (e.g., 017XXXXXXXX).');
            }

            execute_sql('UPDATE app_users SET full_name = ?, phone = ?, address = ? WHERE id = ?', [$fullName, $phone, $address, $userId]);
            $_SESSION['user']['full_name'] = $fullName;
            flash('success', 'Your profile details have been updated.');
            break;

        case 'cart_add':
            (new \App\Controllers\CartController())->add();
            break;

        case 'cart_update':
            (new \App\Controllers\CartController())->update();
            break;

        case 'cart_remove':
            (new \App\Controllers\CartController())->remove();
            break;

        case 'cart_clear':
            (new \App\Controllers\CartController())->clear();
            break;

        case 'order_create':
            (new \App\Controllers\OrderController())->store();
            break;

        case 'order_update_status':
            (new \App\Controllers\OrderController())->updateStatus();
            break;

        case 'order_cancel_customer':
            (new \App\Controllers\OrderController())->cancelByCustomer();
            break;

        case 'prescription_review':
            (new \App\Controllers\OrderController())->reviewPrescription();
            break;
        case 'category_save':
            $id = (int)($_POST['id'] ?? 0);
            $name = required_text('name', 80);
            $description = optional_text('description', 255);
            if ($id) {
                if (!execute_sql('UPDATE categories SET name=?, description=? WHERE id=?', [$name,$description,$id])) {
                    if (!fetch_one('SELECT id FROM categories WHERE id=?', [$id])) throw new DomainException('Category does not exist.');
                }
            } else execute_sql('INSERT INTO categories (name,description) VALUES (?,?)', [$name,$description]);
            flash('success','Category saved successfully.'); break;
        case 'category_delete':
            $id = positive_int($_POST['id'] ?? 0, 'Category ID');
            if ((int)scalar('SELECT COUNT(*) FROM medicines WHERE category_id=?', [$id]) > 0) throw new DomainException('This category is used by medicines and cannot be deleted.');
            execute_sql('DELETE FROM categories WHERE id=?', [$id]);
            flash('success','Category deleted.'); break;
        case 'supplier_save':
            $id = (int)($_POST['id'] ?? 0);
            $values = [required_text('name',100), optional_text('company_name',120), required_text('phone',30),optional_text('email',120),optional_text('address',255)];
            if ($values[3] && !filter_var($values[3], FILTER_VALIDATE_EMAIL)) throw new DomainException('Enter a valid email.');
            if ($id) {
                execute_sql('UPDATE suppliers SET name=?,company_name=?,phone=?,email=?,address=? WHERE id=?', [...$values,$id]);
                if (!fetch_one('SELECT id FROM suppliers WHERE id=?', [$id])) throw new DomainException('Supplier does not exist.');
            } else execute_sql('INSERT INTO suppliers (name,company_name,phone,email,address) VALUES (?,?,?,?,?)', $values);
            flash('success','Supplier saved successfully.'); break;
        case 'supplier_delete':
            $id = positive_int($_POST['id'] ?? 0, 'Supplier ID');
            if ((int)scalar('SELECT COUNT(*) FROM medicines WHERE supplier_id=?', [$id]) > 0) throw new DomainException('This supplier is used by medicines and cannot be deleted.');
            execute_sql('DELETE FROM suppliers WHERE id=?', [$id]);
            flash('success','Supplier deleted.'); break;
        case 'medicine_save':
            $id = (int)($_POST['id'] ?? 0);
            $fields = [
                'name' => required_text('name',120), 'generic_name' => required_text('generic_name',120),
                'brand_name' => optional_text('brand_name',120), 'batch_number' => required_text('batch_number',60),
                'medicine_type' => (string)($_POST['medicine_type'] ?? ''),
                'purchase_price' => price_value($_POST['purchase_price'] ?? '', 'Purchase price'),
                'selling_price' => price_value($_POST['selling_price'] ?? '', 'Selling price'),
                'quantity' => positive_int($_POST['quantity'] ?? '', 'Quantity',true),
                'minimum_stock_level' => positive_int($_POST['minimum_stock_level'] ?? '', 'Minimum stock',true),
                'manufacturing_date' => optional_text('manufacturing_date',10),
                'expiry_date' => required_text('expiry_date',10),
                'prescription_required' => isset($_POST['prescription_required']) ? 1 : 0,
                'category_id' => positive_int($_POST['category_id'] ?? '', 'Category ID'),
                'supplier_id' => positive_int($_POST['supplier_id'] ?? '', 'Supplier ID'),
            ];
            if (!array_key_exists($fields['medicine_type'], medicine_types())) throw new DomainException('Select a valid medicine type.');
            foreach (['manufacturing_date','expiry_date'] as $key) {
                if ($fields[$key] !== null) {
                    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $fields[$key]);
                    if (!$date || $date->format('Y-m-d') !== $fields[$key]) throw new DomainException('Enter a valid ' . str_replace('_',' ', $key) . '.');
                }
            }
            if ($fields['manufacturing_date'] !== null && $fields['expiry_date'] <= $fields['manufacturing_date']) throw new DomainException('Expiry date must be after manufacturing date.');
            if (!fetch_one('SELECT id FROM categories WHERE id=?',[$fields['category_id']]) || !fetch_one('SELECT id FROM suppliers WHERE id=?',[$fields['supplier_id']])) throw new DomainException('Choose an existing category and supplier.');
            $existing = fetch_one('SELECT id FROM medicines WHERE LOWER(batch_number) = LOWER(?) AND id<>?', [$fields['batch_number'], $id]);
            if ($existing) throw new DomainException('Another medicine already uses this batch number.');
            $names = array_keys($fields);
            if ($id) {
                if (!fetch_one('SELECT id FROM medicines WHERE id=?',[$id])) throw new DomainException('Medicine not found.');
                $set = implode(', ', array_map(fn($key) => "$key=?", $names));
                execute_sql("UPDATE medicines SET $set WHERE id=?", [...array_values($fields),$id]);
            } else {
                $columns = implode(',',$names);
                $holders = implode(',',array_fill(0,count($names),'?'));
                execute_sql("INSERT INTO medicines ($columns) VALUES ($holders)",array_values($fields));
            }
            flash('success','Medicine saved successfully.'); break;
        case 'medicine_toggle':
            $id = positive_int($_POST['id'] ?? 0,'Medicine ID');
            $medicine = fetch_one('SELECT id,active FROM medicines WHERE id=?',[$id]);
            if (!$medicine) throw new DomainException('Medicine not found.');
            execute_sql('UPDATE medicines SET active=? WHERE id=?',[(int)!$medicine['active'],$id]);
            flash('success', $medicine['active'] ? 'Medicine deactivated.' : 'Medicine activated.'); break;
        case 'user_create':
            $fullName = required_text('full_name',80);
            $username = required_text('username',50);
            $password = (string)($_POST['password'] ?? '');
            $role = (string)($_POST['role'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/',$username)) throw new DomainException('Username must contain 3–50 letters, numbers, periods, underscores or hyphens.');
            if (strlen($password) < 8 || strlen($password) > 72) throw new DomainException('Password must contain 8–72 characters.');
            if (!in_array($role,['ADMIN','STAFF'],true)) throw new DomainException('Invalid role.');
            execute_sql('INSERT INTO app_users (full_name,username,password,role,enabled) VALUES (?,?,?,?,?)',[$fullName,$username,password_hash($password,PASSWORD_BCRYPT),$role,isset($_POST['enabled'])?1:0]);
            flash('success','User account created.'); break;
        case 'user_toggle':
            $id = positive_int($_POST['id'] ?? 0,'User ID');
            if ($id === (int)user()['id']) throw new DomainException('You cannot disable your own account.');
            $target = fetch_one('SELECT id, enabled, role FROM app_users WHERE id=?',[$id]);
            if (!$target) throw new DomainException('User not found.');
            if ($target['role'] === 'ADMIN' && (int)$target['enabled'] && (int)scalar("SELECT COUNT(*) FROM app_users WHERE role='ADMIN' AND enabled=1") <= 1) throw new DomainException('You cannot disable the last active administrator.');
            execute_sql('UPDATE app_users SET enabled=? WHERE id=?',[(int)!$target['enabled'],$id]);
            flash('success','User status updated.'); break;
        case 'sale_create':
            $customer = optional_text('customer_name',100);
            $phone = optional_text('customer_phone',30);
            $ids = $_POST['medicine_id'] ?? [];
            $qtys = $_POST['quantity'] ?? [];
            if (!is_array($ids) || !is_array($qtys) || count($ids) !== count($qtys) || count($ids) < 1 || count($ids) > 40) throw new DomainException('Add between 1 and 40 medicine rows.');
            $requested = [];
            foreach ($ids as $i => $rawId) {
                if ((string)$rawId === '') continue;
                $medicineId = positive_int($rawId,'Medicine ID');
                $quantity = positive_int($qtys[$i] ?? 0,'Sale quantity');
                $requested[$medicineId] = ($requested[$medicineId] ?? 0) + $quantity;
                if ($requested[$medicineId] > 1000000) throw new DomainException('Requested quantity is too high.');
            }
            if (!$requested) throw new DomainException('Add at least one medicine to the sale.');
            ksort($requested, SORT_NUMERIC);
            $discount = (int)round((float)price_value($_POST['discount'] ?? '0','Discount',true) * 100);
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $subtotal = 0;
                $lines = [];
                foreach ($requested as $medicineId => $quantity) {
                    $medicine = InventoryService::deductBatchStock($pdo, $medicineId, $quantity);
                    $unitCents = (int)round((float)$medicine['selling_price'] * 100);
                    $lineCents = $unitCents * $quantity;
                    $subtotal += $lineCents;
                    $lines[] = [$medicineId,$quantity,$unitCents,$lineCents];
                }
                if ($discount > $subtotal) throw new DomainException('Discount cannot exceed the subtotal.');
                $invoice = 'INV-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
                $stmt = $pdo->prepare('INSERT INTO sales (invoice_number,sale_date,customer_name,customer_phone,subtotal,discount,total_amount,sold_by_id) VALUES (?,NOW(),?,?,?,?,?,?)');
                $stmt->execute([$invoice,$customer,$phone,number_format($subtotal/100,2,'.',''),number_format($discount/100,2,'.',''),number_format(($subtotal-$discount)/100,2,'.',''),user()['id']]);
                $saleId = (int)$pdo->lastInsertId();
                $add = $pdo->prepare('INSERT INTO sale_items(sale_id,medicine_id,quantity,unit_price,line_total) VALUES (?,?,?,?,?)');
                foreach ($lines as [$medicineId,$quantity,$unitCents,$lineCents]) {
                    $add->execute([$saleId,$medicineId,$quantity,number_format($unitCents/100,2,'.',''),number_format($lineCents/100,2,'.','')]);
                }
                $pdo->commit();
            } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $ex; }
            flash('success','Sale completed successfully. Invoice is ready.');
            redirect('invoice',['id'=>$saleId]);
        default:
            throw new DomainException('Unknown action.');
    }
    redirect($fallback);
} catch (DomainException $ex) {
    flash('error', $ex->getMessage());
} catch (PDOException $ex) {
    $mysqlCode = $ex->errorInfo[1] ?? 0;
    $message = match($mysqlCode) {
        1062 => 'This name, username or batch number is already in use.',
        1451 => 'This record is in use and cannot be deleted.',
        1452 => 'Please select valid related records.',
        default => 'Database operation failed. Please check your input and database connection.'
    };
    flash('error', $message);
}
redirect($fallback);
