<?php
session_start();

// If already logged in, redirect to role-specific dashboard
if (isset($_SESSION['user_id'])) {
    $role = strtolower($_SESSION['role']);
    if ($role === 'admin') {
        header('Location: admin/dashboard.php');
        exit();
    } elseif ($role === 'cashier') {
        header('Location: cashier/dashboard.php');
        exit();
    } elseif ($role === 'customer') {
        header('Location: customer/dashboard.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes" />
    <title>CHIBUGAN · Customer Portal</title>
    <link rel="icon" type="image/x-icon" href="logo.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <link rel="stylesheet" href="css/index.css">
    <style>
        /* Additional styles for unified staff login */
        .staff-login-note {
            font-size: 0.75rem;
            color: #6c757d;
            text-align: center;
            margin-top: 10px;
        }
        
        /* New section styles */
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 30px 20px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .feature-icon i {
            font-size: 35px;
            color: white;
        }
        .rating-star {
            color: #ffc107;
            font-size: 1.2rem;
        }
        .testimonial-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: transform 0.3s;
            height: 100%;
        }
        .testimonial-card:hover {
            transform: translateY(-5px);
        }
        .testimonial-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
        }
        .menu-preview-img {
            height: 200px;
            object-fit: cover;
            border-radius: 15px;
            width: 100%;
        }
        .stats-counter {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2e7d32;
        }
        .gallery-img {
            height: 250px;
            object-fit: cover;
            border-radius: 15px;
            transition: transform 0.3s;
            width: 100%;
        }
        .gallery-img:hover {
            transform: scale(1.03);
        }
        .cta-section {
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            border-radius: 30px;
            padding: 60px 40px;
            color: white;
        }
        @media (max-width: 768px) {
            .stats-counter {
                font-size: 1.8rem;
            }
            .cta-section {
                padding: 40px 20px;
            }
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
                    <img src="logo.jpg" 
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
                        <li class="nav-item"><a href="#features" class="nav-link">Features</a></li>
                        <li class="nav-item"><a href="#reviews" class="nav-link">Reviews</a></li>
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
                    <li class="nav-item"><a href="#features" class="nav-link text-white" data-bs-dismiss="offcanvas">Features</a></li>
                    <li class="nav-item"><a href="#menu" class="nav-link text-white" data-bs-dismiss="offcanvas">Menu</a></li>
                    <li class="nav-item"><a href="#reviews" class="nav-link text-white" data-bs-dismiss="offcanvas">Reviews</a></li>
                    <li class="nav-item"><a href="#" class="nav-link text-white" data-bs-toggle="modal" data-bs-target="#customerLogin" data-bs-dismiss="offcanvas">Login</a></li>
                    <li class="nav-item"><a href="#contact" class="nav-link text-white" data-bs-dismiss="offcanvas">Contact</a></li>
                </ul>
            </div>
        </div>

        <div class="hero-content">
            <h1 class="display-3 fw-bold">Welcome to CHIBUGAN</h1>
            <p class="lead mb-4">Freshly cooked meals · Filipino comfort food</p>
            <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#customerLogin">
                <i class="fas fa-sign-in-alt me-2"></i>Login
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
                    <div class="mt-4">
                        <div class="row">
                            <div class="col-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-utensils text-success fs-3 me-3"></i>
                                    <div>
                                        <h5 class="mb-0 fw-bold">30+</h5>
                                        <small class="text-muted">Delicious Dishes</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-smile text-success fs-3 me-3"></i>
                                    <div>
                                        <h5 class="mb-0 fw-bold">1000+</h5>
                                        <small class="text-muted">Happy Customers</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-clock text-success fs-3 me-3"></i>
                                    <div>
                                        <h5 class="mb-0 fw-bold">Since 1995</h5>
                                        <small class="text-muted">Years of Service</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 mb-3">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-star text-success fs-3 me-3"></i>
                                    <div>
                                        <h5 class="mb-0 fw-bold">4.8</h5>
                                        <small class="text-muted">Customer Rating</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card-custom overflow-hidden">
                        <img src="https://images.unsplash.com/photo-1555939594-58d7cb561ad1?auto=format&fit=crop&w=800&q=80" 
                             class="card-img-top" alt="local ingredients" style="height: 260px; object-fit: cover; width: 100%;" />
                        <div class="card-body text-center">
                            <h5 class="fw-bold text-success">Community kitchen</h5>
                            <p class="text-muted">Serving Panabo City with warmth since 1995.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== FEATURES SECTION ========== -->
    <section id="features" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-success">Why Choose CHIBUGAN?</h2>
                <p class="lead text-muted">Experience the best of Filipino cuisine with these amazing benefits</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon mx-auto">
                            <i class="fas fa-motorcycle"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Fast Delivery</h5>
                        <p class="text-muted">Get your favorite meals delivered to your doorstep within 30-45 minutes.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon mx-auto">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Easy Payments</h5>
                        <p class="text-muted">Pay via credit balance, GCash, or cash on delivery. Convenient and secure.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon mx-auto">
                            <i class="fas fa-leaf"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Fresh Ingredients</h5>
                        <p class="text-muted">We source fresh, quality ingredients from local markets every single day.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon mx-auto">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Affordable Prices</h5>
                        <p class="text-muted">Delicious meals starting at just ₱50. Great taste without breaking the bank.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon mx-auto">
                            <i class="fas fa-gift"></i>
                        </div>
                        <h5 class="fw-bold mb-3">Welcome Bonus</h5>
                        <p class="text-muted">New customers get ₱50 credit bonus on their first sign-up!</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon mx-auto">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h5 class="fw-bold mb-3">24/7 Support</h5>
                        <p class="text-muted">Our customer support team is available round the clock to assist you.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>



    <!-- ========== CUSTOMER REVIEWS SECTION ========== -->
    <section id="reviews" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold text-success">What Our Customers Say</h2>
                <p class="lead text-muted">Join thousands of satisfied customers who love CHIBUGAN</p>
                <div class="mt-2">
                    <i class="fas fa-star rating-star"></i>
                    <i class="fas fa-star rating-star"></i>
                    <i class="fas fa-star rating-star"></i>
                    <i class="fas fa-star rating-star"></i>
                    <i class="fas fa-star rating-star"></i>
                    <span class="ms-2 fw-bold fs-5">4.8</span>
                    <span class="text-muted">(1,234 reviews)</span>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-3">
                            <div class="testimonial-avatar me-3">
                                M
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0">Maria Santos</h6>
                                <div class="small text-muted">Customer since 2023</div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                        </div>
                        <p class="text-muted mb-0">"The best adobo in town! The portion sizes are generous and delivery is always fast. Highly recommended!"</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-3">
                            <div class="testimonial-avatar me-3">
                                J
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0">John Dela Cruz</h6>
                                <div class="small text-muted">Customer since 2024</div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                        </div>
                        <p class="text-muted mb-0">"Great value for money! The credit system is very convenient. I order here at least twice a week."</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-3">
                            <div class="testimonial-avatar me-3">
                                A
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0">Anna Reyes</h6>
                                <div class="small text-muted">Customer since 2022</div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star-half-alt rating-star"></i>
                        </div>
                        <p class="text-muted mb-0">"Their sinigang tastes just like home-cooked meals. Love the friendly staff and quick service!"</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-3">
                            <div class="testimonial-avatar me-3">
                                R
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0">Ramon Garcia</h6>
                                <div class="small text-muted">Customer since 2024</div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                        </div>
                        <p class="text-muted mb-0">"The crispy pata is amazing! Will definitely order again. Best carinderia in Panabo City!"</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-3">
                            <div class="testimonial-avatar me-3">
                                L
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0">Liza Fernandez</h6>
                                <div class="small text-muted">Customer since 2023</div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                        </div>
                        <p class="text-muted mb-0">"Love the ₱50 welcome bonus! The website is easy to use and food is always fresh."</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-3">
                            <div class="testimonial-avatar me-3">
                                M
                            </div>
                            <div>
                                <h6 class="fw-bold mb-0">Michael Tan</h6>
                                <div class="small text-muted">Customer since 2024</div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                            <i class="fas fa-star rating-star"></i>
                        </div>
                        <p class="text-muted mb-0">"Best bang for your buck! The lechon kawali is crispy and delicious. 10/10 recommend!"</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== STATS COUNTER SECTION ========== -->
    <section class="py-5 bg-success text-white">
        <div class="container">
            <div class="row text-center g-4">
                <div class="col-md-3 col-6">
                    <div class="stats-counter">5000+</div>
                    <p class="mb-0">Orders Served</p>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stats-counter">30+</div>
                    <p class="mb-0">Menu Items</p>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stats-counter">1000+</div>
                    <p class="mb-0">Happy Customers</p>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stats-counter">28+</div>
                    <p class="mb-0">Years Serving</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== CTA SECTION ========== -->
    <section class="py-5">
        <div class="container">
            <div class="cta-section text-center">
                <h2 class="fw-bold mb-3">Ready to Satisfy Your Cravings?</h2>
                <p class="lead mb-4">Sign up today and get ₱50 welcome credit!</p>
                <button class="btn btn-light btn-lg px-5 fw-bold" data-bs-toggle="modal" data-bs-target="#customerSignup">
                    <i class="fas fa-user-plus me-2"></i>Sign Up Now
                </button>
                <p class="mt-3 mb-0 opacity-75">Already have an account? <a href="#" class="text-white fw-bold" data-bs-toggle="modal" data-bs-target="#customerLogin">Login here</a></p>
            </div>
        </div>
    </section>

    <!-- ========== FOOTER ========== -->
    <footer id="contact" class="py-4 bg-dark text-white">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5><i class="fas fa-store-alt me-2"></i>CHIBUGAN Carinderia</h5>
                    <p class="text-white-50">Fresh Filipino meals since 1995.</p>
                    <div class="mt-3">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-twitter fa-lg"></i></a>
                    </div>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5>Contact Us</h5>
                    <p class="text-white-50 mb-1"><i class="fas fa-envelope me-2"></i> chibugan@gmail.com</p>
                    <p class="text-white-50 mb-1"><i class="fas fa-phone-alt me-2"></i> 0995-102-1259</p>
                    <p class="text-white-50"><i class="fas fa-map-marker-alt me-2"></i> Brgy. Cagangohan, Panabo City</p>
                </div>
                <div class="col-md-4">
                    <h5>Opening Hours</h5>
                    <p class="text-white-50 mb-1">Monday - Saturday: 8:00 AM - 8:00 PM</p>
                    <p class="text-white-50">Sunday: 9:00 AM - 6:00 PM</p>
                </div>
            </div>
            <hr class="border-secondary" />
            <p class="text-center mb-0 text-white-50">&copy; 2025 CHIBUGAN. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- ========== LOGIN MODALS ========== (Keep all existing modals here) ========== -->
    
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
                    <button type="submit" class="btn btn-success w-100 fw-bold py-2">Login as Customer</button>
                </form>
                <hr class="my-3">
                <div class="text-center">
                    <p class="mb-2 text-muted small">Staff Access?</p>
                    <button class="btn btn-outline-secondary w-100 py-2" data-bs-toggle="modal" data-bs-target="#staffLogin" data-bs-dismiss="modal">
                        <i class="fas fa-user-shield me-2"></i> Staff Login
                    </button>
                </div>
                <p class="text-center mt-3 small">New here? <a href="#" data-bs-toggle="modal" data-bs-target="#customerSignup" class="text-success fw-bold" data-bs-dismiss="modal">Sign Up</a></p>
            </div>
        </div>
    </div>

    <!-- Staff Login Modal -->
    <div class="modal fade" id="staffLogin" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4">
                <div class="text-center mb-3">
                    <h4 class="fw-bold text-danger">Staff Login</h4>
                    <p class="text-muted">Enter your credentials to access the staff panel</p>
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                </div>
                <form id="staffLoginForm">
                    <div class="mb-3">
                        <input type="email" id="staffEmail" class="form-control" placeholder="Email address" required />
                    </div>
                    <div class="mb-3">
                        <input type="password" id="staffPassword" class="form-control" placeholder="Password" required />
                    </div>
                    <button type="submit" class="btn btn-danger w-100 fw-bold py-2">
                        <i class="fas fa-sign-in-alt me-2"></i>Login as Staff
                    </button>
                </form>
                <div class="staff-login-note">
                    <i class="fas fa-info-circle me-1"></i> System will automatically detect your role (Admin or Cashier)
                </div>
                <p class="text-center mt-3 small"><a href="#" data-bs-toggle="modal" data-bs-target="#customerLogin" class="text-success" data-bs-dismiss="modal">← Back to Customer Login</a></p>
            </div>
        </div>
    </div>

    <!-- Customer Signup Modal -->
    <div class="modal fade" id="customerSignup" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content p-4">
                <div class="text-center mb-3">
                    <h4 class="fw-bold text-success">Create Account</h4>
                    <p class="text-muted">Get ₱50 welcome bonus!</p>
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                </div>
                <form id="signupForm">
                    <div class="mb-3">
                        <input type="text" id="signupName" class="form-control" placeholder="Full Name" required />
                    </div>
                    <div class="mb-3">
                        <input type="tel" id="signupPhone" class="form-control" placeholder="Phone number" required />
                    </div>
                    <div class="mb-3">
                        <input type="email" id="signupEmail" class="form-control" placeholder="Email" required />
                    </div>
                    <div class="mb-3">
                        <input type="password" id="signupPassword" class="form-control" placeholder="Password (min 6 chars)" required />
                    </div>
                    <div class="mb-3">
                        <input type="password" id="signupConfirmPassword" class="form-control" placeholder="Confirm password" required />
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="termsUi" required />
                        <label class="form-check-label small" for="termsUi">I agree to Terms of Service</label>
                    </div>
                    <button type="submit" class="btn btn-success w-100 fw-bold py-2">
                        <i class="fas fa-user-plus me-2"></i>Sign Up
                    </button>
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
                    <p class="text-muted">Enter your email to receive reset link</p>
                    <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"></button>
                </div>
                <form id="forgotPasswordForm">
                    <div class="mb-3">
                        <input type="email" id="resetEmail" class="form-control" placeholder="Enter your email" required />
                    </div>
                    <button type="submit" class="btn btn-success w-100 py-2">Send Reset Link</button>
                </form>
                <p class="text-center mt-3 small"><a href="#" data-bs-toggle="modal" data-bs-target="#customerLogin" class="text-success" data-bs-dismiss="modal">← Back to Login</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function showToast(message, type = 'success') {
            const toastContainer = document.querySelector('.toast-notification');
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show shadow`;
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

        // Customer Login
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

        // Staff Login
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

        // Signup
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
            
            if (!document.getElementById('termsUi').checked) {
                showToast('Please accept the Terms of Service!', 'warning');
                return;
            }
            
            const formData = {
                email: document.getElementById('signupEmail').value,
                password: password,
                full_name: document.getElementById('signupName').value,
                phone: document.getElementById('signupPhone').value
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

        // Forgot Password
        document.getElementById('forgotPasswordForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const email = document.getElementById('resetEmail').value;
            
            showSpinner(true);
            
            setTimeout(() => {
                showSpinner(false);
                showToast(`Password reset link sent to ${email}`, 'info');
                bootstrap.Modal.getInstance(document.getElementById('forgotPassword')).hide();
                document.getElementById('forgotPasswordForm').reset();
            }, 1500);
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