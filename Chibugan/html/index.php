<?php
session_start();

// If already logged in, redirect to role-specific dashboard
if (isset($_SESSION['user_id'])) {
    $role = strtolower($_SESSION['role']);
    if ($role === 'admin') {
        header('Location: admin/dashboard.php');
    } elseif ($role === 'cashier') {
        header('Location: cashier/dashboard.php');
    } elseif ($role === 'customer') {
        header('Location: customer/dashboard.php');
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes" />
    <title>CHIBUGAN · Customer Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f9fafb;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #212529;
        }

        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
        }

        .hero-section {
            position: relative;
            height: 100vh;
            min-height: 560px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .hero-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            filter: brightness(55%);
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            color: #fff;
            padding: 0 1rem;
        }

        .hero-content h1 {
            font-weight: 700;
            letter-spacing: -0.5px;
            text-shadow: 2px 2px 10px rgba(0,0,0,0.4);
        }

        .navbar-logo {
            height: 42px;
            width: 42px;
            object-fit: cover;
            border-radius: 50%;
            margin-right: 8px;
            border: 2px solid rgba(255,255,255,0.7);
        }

        #mainNav {
            transition: all 0.4s ease;
            z-index: 1030;
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
        }

        #mainNav.navbar-transparent {
            background: transparent !important;
            box-shadow: none;
        }

        #mainNav.navbar-transparent .nav-link {
            color: #ffffff !important;
            font-weight: 500;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.3);
        }

        #mainNav.navbar-transparent .navbar-brand {
            color: #ffffff !important;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.3);
        }

        #mainNav.navbar-solid {
            background: #198754 !important;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        #mainNav.navbar-solid .nav-link {
            color: #ffffff !important;
            font-weight: 500;
        }

        .offcanvas-dark {
            background-color: #1e1e2f;
            color: #f0f0f0;
        }

        .btn-success {
            background-color: #198754;
            border-color: #198754;
            border-radius: 3rem;
            padding: 0.6rem 1.8rem;
            font-weight: 600;
        }

        footer {
            background-color: #1e1e2f;
            color: #ddd;
        }

        .modal-content {
            border: none;
            border-radius: 1.5rem;
            overflow: hidden;
        }

        .form-control {
            border-radius: 2.5rem;
            padding: 0.7rem 1.2rem;
            border: 1px solid #dee2e6;
        }

        .form-control:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.18rem rgba(25,135,84,0.2);
        }

        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 10000;
            display: none;
            justify-content: center;
            align-items: center;
        }

        .spinner-overlay.show {
            display: flex;
        }
    </style>
</head>
<body>
    <div class="toast-notification"></div>
    <div class="spinner-overlay" id="spinnerOverlay">
        <div class="spinner-border text-light" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- ========== HERO + NAVIGATION ========== -->
    <div class="hero-section" id="home">
        <img src="https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1350&q=80" 
             alt="Filipino dishes" class="hero-image" />

        <nav id="mainNav" class="navbar navbar-expand-lg fixed-top navbar-transparent">
            <div class="container">
                <a class="navbar-brand fw-bold" href="#home">
                    <img src="https://via.placeholder.com/100x100/198754/FFFFFF?text=CH" 
                         alt="CHIBUGAN logo" class="navbar-logo" />
                    CHIBUGAN
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse d-none d-lg-flex" id="desktopNav">
                    <ul class="navbar-nav ms-auto align-items-center">
                        <li class="nav-item"><a href="#home" class="nav-link">Home</a></li>
                        <li class="nav-item"><a href="#about" class="nav-link">About</a></li>
                        <li class="nav-item"><a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#customerLogin">Login</a></li>
                        <li class="nav-item"><a href="#contact" class="nav-link">Contact</a></li>
                    </ul>
                </div>
            </div>
        </nav>

        <div class="offcanvas offcanvas-end offcanvas-dark d-lg-none" tabindex="-1" id="mobileNav">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title fw-bold text-white">CHIBUGAN</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <ul class="navbar-nav">
                    <li class="nav-item"><a href="#home" class="nav-link text-white" data-bs-dismiss="offcanvas">Home</a></li>
                    <li class="nav-item"><a href="#about" class="nav-link text-white" data-bs-dismiss="offcanvas">About</a></li>
                    <li class="nav-item"><a href="#" class="nav-link text-white" data-bs-toggle="modal" data-bs-target="#customerLogin" data-bs-dismiss="offcanvas">Login</a></li>
                    <li class="nav-item"><a href="#contact" class="nav-link text-white" data-bs-dismiss="offcanvas">Contact</a></li>
                </ul>
            </div>
        </div>

        <div class="hero-content">
            <h1 class="display-3 fw-bold">Welcome to CHIBUGAN</h1>
            <p class="lead mb-4">Freshly cooked meals · Filipino comfort food</p>
            <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#customerLogin">
                <i class="fas fa-utensils me-2"></i>Get Started
            </button>
        </div>
    </div>

    <!-- ========== ABOUT SECTION ========== -->
    <section id="about" class="py-5 bg-white">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <h2 class="fw-bold text-success mb-3">About CHIBUGAN Carinderia</h2>
                    <p class="fs-5 fw-light">Authentic Filipino taste, served fresh every day.</p>
                    <p>We prepare home‑cooked meals inspired by traditional recipes. Our ingredients are sourced daily from local markets — from savory adobo to comforting sinigang, every dish is made with care.</p>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1555939594-58d7cb561ad1?auto=format&fit=crop&w=800&q=80" 
                             class="card-img-top" alt="local ingredients" style="height: 260px; object-fit: cover;" />
                        <div class="card-body text-center">
                            <h5 class="fw-bold text-success">Community kitchen</h5>
                            <p class="text-muted">Serving Panabo City with warmth since 1995.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== FOOTER ========== -->
    <footer id="contact" class="py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-store-alt me-2"></i>CHIBUGAN Carinderia</h5>
                    <p>Fresh Filipino meals since 1995. Brgy. Cagangohan, Panabo City.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h5>Contact</h5>
                    <p><i class="fas fa-envelope me-1"></i> chibugan@gmail.com<br/>
                    <i class="fas fa-phone-alt me-1"></i> 0995-102-1259</p>
                </div>
            </div>
            <hr class="border-secondary" />
            <p class="text-center mb-0">&copy; 2025 CHIBUGAN. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- ========== LOGIN MODALS ========== -->
    
    <!-- Customer Login Modal -->
    <div class="modal fade" id="customerLogin" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4">
                <div class="text-center mb-3">
                    <h4 class="fw-bold text-success">Customer Login</h4>
                    <p class="text-muted">Sign in to order online.</p>
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                </div>
                <form id="customerLoginForm">
                    <div class="mb-3">
                        <input type="email" id="customerEmail" class="form-control" placeholder="Email address" required />
                    </div>
                    <div class="mb-3">
                        <input type="password" id="customerPassword" class="form-control" placeholder="Password" required />
                    </div>
                    <div class="d-flex justify-content-end mb-3">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPassword" class="text-success small" data-bs-dismiss="modal">Forgot password?</a>
                    </div>
                    <button type="submit" class="btn btn-success w-100 fw-bold">Login as Customer</button>
                </form>
                <hr class="my-3">
                <div class="text-center">
                    <p class="mb-2">Staff Access?</p>
                    <button class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#staffLogin" data-bs-dismiss="modal">
                        <i class="fas fa-user-shield me-2"></i> Staff Login
                    </button>
                </div>
                <p class="text-center mt-3 small">New here? <a href="#" data-bs-toggle="modal" data-bs-target="#customerSignup" class="text-success fw-bold" data-bs-dismiss="modal">Sign Up</a></p>
            </div>
        </div>
    </div>

    <!-- Staff Login Modal (Admin & Cashier) -->
    <div class="modal fade" id="staffLogin" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4">
                <div class="text-center mb-3">
                    <h4 class="fw-bold text-danger">Staff Login</h4>
                    <p class="text-muted">Admin or Cashier access only</p>
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                </div>
                <form id="staffLoginForm">
                    <div class="mb-3">
                        <input type="email" id="staffEmail" class="form-control" placeholder="Email address" required />
                    </div>
                    <div class="mb-3">
                        <input type="password" id="staffPassword" class="form-control" placeholder="Password" required />
                    </div>
                    <button type="submit" class="btn btn-danger w-100 fw-bold">Login as Staff</button>
                </form>
                <p class="text-center mt-3 small"><a href="#" data-bs-toggle="modal" data-bs-target="#customerLogin" data-bs-dismiss="modal">← Back to Customer Login</a></p>
            </div>
        </div>
    </div>

    <!-- Customer Signup Modal -->
    <div class="modal fade" id="customerSignup" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4">
                <div class="text-center mb-3">
                    <h4 class="fw-bold text-success">Create Account</h4>
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                </div>
                <form id="signupForm">
                    <input type="text" id="signupName" class="form-control mb-3" placeholder="Full Name" required />
                    <input type="text" id="signupPhone" class="form-control mb-3" placeholder="Phone number" required />
                    <input type="email" id="signupEmail" class="form-control mb-3" placeholder="Email" required />
                    <input type="password" id="signupPassword" class="form-control mb-3" placeholder="Password (min 6 chars)" required />
                    <input type="password" id="signupConfirmPassword" class="form-control mb-3" placeholder="Confirm password" required />
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="termsUi" required />
                        <label class="form-check-label small" for="termsUi">I agree to Terms of Service</label>
                    </div>
                    <button type="submit" class="btn btn-success w-100 fw-bold">Sign Up</button>
                </form>
                <p class="text-center mt-3 small">Already have an account? <a href="#" data-bs-toggle="modal" data-bs-target="#customerLogin" class="text-success fw-bold" data-bs-dismiss="modal">Login</a></p>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPassword" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4">
                <div class="text-center mb-3">
                    <h4 class="fw-bold text-success">Reset Password</h4>
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                </div>
                <form id="forgotPasswordForm">
                    <input type="email" id="resetEmail" class="form-control mb-3" placeholder="Enter your email" required />
                    <button type="submit" class="btn btn-success w-100">Send Reset Link</button>
                </form>
                <p class="text-center mt-3 small"><a href="#" data-bs-toggle="modal" data-bs-target="#customerLogin" data-bs-dismiss="modal">← Back to Login</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function showToast(message, type = 'success') {
            const toastContainer = document.querySelector('.toast-notification');
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
            toast.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            toastContainer.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }

        function showSpinner(show) {
            const spinner = document.getElementById('spinnerOverlay');
            if (show) {
                spinner.classList.add('show');
            } else {
                spinner.classList.remove('show');
            }
        }

        // Customer Login (Only customers can login here)
        document.getElementById('customerLoginForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('customerEmail').value;
            const password = document.getElementById('customerPassword').value;
            
            showSpinner(true);
            
            try {
                const response = await fetch('api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email, password: password, role: 'customer' })
                });
                
                const result = await response.json();
                showSpinner(false);
                
                if (result.success && result.role === 'customer') {
                    showToast('Login successful! Redirecting...');
                    setTimeout(() => window.location.href = 'customer/dashboard.php', 1000);
                } else {
                    showToast('Invalid credentials or not a customer account!', 'danger');
                }
            } catch (error) {
                showSpinner(false);
                showToast('Error: ' + error.message, 'danger');
            }
        });

        // Staff Login (Only Admin or Cashier)
        document.getElementById('staffLoginForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('staffEmail').value;
            const password = document.getElementById('staffPassword').value;
            
            showSpinner(true);
            
            try {
                const response = await fetch('api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email, password: password })
                });
                
                const result = await response.json();
                showSpinner(false);
                
                if (result.success && (result.role === 'admin' || result.role === 'cashier')) {
                    showToast('Login successful! Redirecting...');
                    if (result.role === 'admin') {
                        setTimeout(() => window.location.href = 'admin/dashboard.php', 1000);
                    } else {
                        setTimeout(() => window.location.href = 'cashier/dashboard.php', 1000);
                    }
                } else {
                    showToast('Invalid credentials or not staff account!', 'danger');
                }
            } catch (error) {
                showSpinner(false);
                showToast('Error: ' + error.message, 'danger');
            }
        });

        // Signup (Creates only Customer accounts)
        document.getElementById('signupForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const password = document.getElementById('signupPassword').value;
            const confirmPassword = document.getElementById('signupConfirmPassword').value;
            
            if (password !== confirmPassword) {
                showToast('Passwords do not match!', 'danger');
                return;
            }
            
            if (password.length < 6) {
                showToast('Password must be at least 6 characters!', 'danger');
                return;
            }
            
            const formData = {
                email: document.getElementById('signupEmail').value,
                password: password,
                full_name: document.getElementById('signupName').value,
                phone: document.getElementById('signupPhone').value,
                role: 'Customer'  // Force role as Customer
            };
            
            showSpinner(true);
            
            try {
                const response = await fetch('api/register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                showSpinner(false);
                
                if (result.success) {
                    showToast('Registration successful! Please login.');
                    bootstrap.Modal.getInstance(document.getElementById('customerSignup')).hide();
                    const loginModal = new bootstrap.Modal(document.getElementById('customerLogin'));
                    loginModal.show();
                    document.getElementById('signupForm').reset();
                } else {
                    showToast(result.message || 'Registration failed!', 'danger');
                }
            } catch (error) {
                showSpinner(false);
                showToast('Error: ' + error.message, 'danger');
            }
        });

        // Navbar scroll effect
        document.addEventListener('DOMContentLoaded', function() {
            const navbar = document.getElementById('mainNav');
            const aboutSection = document.getElementById('about');
            
            function updateNavbar() {
                const aboutSectionTop = aboutSection.getBoundingClientRect().top;
                const navbarHeight = navbar.offsetHeight;
                
                if (aboutSectionTop <= navbarHeight) {
                    navbar.classList.remove('navbar-transparent');
                    navbar.classList.add('navbar-solid');
                } else {
                    navbar.classList.add('navbar-transparent');
                    navbar.classList.remove('navbar-solid');
                }
            }
            
            window.addEventListener('scroll', updateNavbar);
            updateNavbar();
        });
    </script>
</body>
</html>