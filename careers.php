<?php
require_once __DIR__ . '/classes/Database.php';
$pdo = Database::connection();
$jobs = $pdo->query("SELECT * FROM jobs WHERE job_deadline >= CURRENT_DATE ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <link rel="icon" type="image/x-icon" href="assets/img/logo.png">

  <title>Careers | Mashirikiano SACCO</title>
  <meta content="View Mashirikiano SACCO career opportunities, internships, attachment openings, and job vacancies." name="description">
  <meta content="Mashirikiano SACCO careers, internships, attachment, jobs, vacancies" name="keywords">
  <meta name="robots" content="index, follow">
  <link rel="canonical" href="https://mashirikianosacco.co.ke/careers.php">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,600;1,700&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="assets/css/main.css" rel="stylesheet">
</head>

<body>

  <!-- ======= Header ======= -->
  <header id="header" class="header d-flex align-items-center fixed-top">
    <div class="container-fluid container-xl d-flex align-items-center justify-content-between">
      <a href="index.php" class="logo d-flex align-items-center">
        <img src="assets/img/logo.png" alt="Mashirikiano SACCO logo">
        <h5 class="ms-2 mb-0" style="font-size: 18px; font-weight: 700; white-space: nowrap; margin-top: 5px;">Mashirikiano</h5>
      </a>
      <i class="mobile-nav-toggle mobile-nav-show bi bi-list order-last ms-1"></i>
      <i class="mobile-nav-toggle mobile-nav-hide d-none bi bi-x order-last ms-1"></i>
      <nav id="navbar" class="navbar order-lg-1">
        <ul>
          <li><a href="index.php">Home</a></li>
          <li><a href="about.php">About</a></li>
          <li class="dropdown"><a href="#"><span>Services</span> <i class="bi bi-chevron-down dropdown-indicator"></i></a>
            <ul>
              <li><a href="loans.php">Loans</a></li>
              <li><a href="loan-security-collateral-services.php">Loan Security &amp; Collateral Services</a></li>
              <li><a href="payments.php">Payments</a></li>
            </ul>
          </li>
          <li class="dropdown"><a href="#" class="active"><span>Resources</span> <i class="bi bi-chevron-down dropdown-indicator"></i></a>
            <ul>
              <li><a href="forms-downloads.php">Forms &amp; Downloads</a></li>
              <li><a href="careers.php">Careers</a></li>
              <li><a href="events.php">Events</a></li>
            </ul>
          </li>
          <li><a href="membership.php">Membership</a></li>
          <li><a href="contact.php">Contact</a></li>
          <li><a href="faq.php">FAQ</a></li>
          <li><a class="get-a-quote" href="auth/login.php">Member Login</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <main id="main">

    <!-- Career Hero -->
    <section class="career-hero-stima" data-aos="fade">
      <div class="container">
        <h1>BUILD A CAREER WITH MASHIRIKIANO SACCO</h1>
        <p>Join a team of innovative professionals dedicated to financial excellence and community growth.</p>
      </div>
    </section>

    <!-- Stats Section -->
    <section class="career-stats-section">
      <div class="container">
        <div class="row g-4 justify-content-center">
          <div class="col-6 col-md-3" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-item">
              <i class="bi bi-people"></i>
              <span class="count">50+</span>
              <p>Employees</p>
            </div>
          </div>
          <div class="col-6 col-md-3" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-item">
              <i class="bi bi-building"></i>
              <span class="count">2+</span>
              <p>Branches</p>
            </div>
          </div>
          <div class="col-6 col-md-3" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-item">
              <i class="bi bi-person-check"></i>
              <span class="count">10k+</span>
              <p>Active Members</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Culture Section -->
    <section class="py-5">
      <div class="container" data-aos="fade-up">
        <div class="row justify-content-center text-center">
          <div class="col-lg-8">
            <h2 class="fw-bold mb-4">Working at Mashirikiano SACCO</h2>
            <p class="text-muted lead">At Mashirikiano SACCO, we believe our people are our greatest asset. We foster a culture of innovation, integrity, and professional growth. Whether you are an intern looking for your first step or an experienced professional, we provide the environment and tools you need to excel.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Departments Section -->
    <section class="career-departments" data-aos="fade-up">
      <div class="container">
        <h2>OUR DEPARTMENTS</h2>
        <div class="dept-grid">
          <div class="dept-item"><i class="bi bi-laptop"></i><span>ICT & Systems</span></div>
          <div class="dept-item"><i class="bi bi-cash-stack"></i><span>Finance & Accounts</span></div>
          <div class="dept-item"><i class="bi bi-person-badge"></i><span>Human Resources</span></div>
          <div class="dept-item"><i class="bi bi-megaphone"></i><span>Marketing & PR</span></div>
          <div class="dept-item"><i class="bi bi-shield-check"></i><span>Internal Audit</span></div>
          <div class="dept-item"><i class="bi bi-cart-check"></i><span>Procurement</span></div>
          <div class="dept-item"><i class="bi bi-graph-up-arrow"></i><span>Credit Management</span></div>
          <div class="dept-item"><i class="bi bi-people-fill"></i><span>Member Services</span></div>
          <div class="dept-item"><i class="bi bi-briefcase"></i><span>Legal & Compliance</span></div>
          <div class="dept-item"><i class="bi bi-gear"></i><span>Operations</span></div>
          <div class="dept-item"><i class="bi bi-journal-check"></i><span>Risk Management</span></div>
          <div class="dept-item"><i class="bi bi-headset"></i><span>Customer Support</span></div>
        </div>
      </div>
    </section>

    <!-- Job Listings -->
    <section class="job-listing-container">
      <?php if (empty($jobs)): ?>
        <div class="job-block job-block-light" data-aos="fade-up">
          <div class="container">
            <i class="bi bi-info-circle text-muted" style="font-size: 3rem;"></i>
            <h2 class="mt-4">No Current Openings</h2>
            <p>There are no active job openings at the moment. Please check back later or follow us on our social media for updates.</p>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($jobs as $index => $job): ?>
          <div class="job-block <?= ($index % 2 === 0) ? 'job-block-light' : 'job-block-dark' ?>" data-aos="fade-up">
            <div class="container">
              <div class="apply-graphic">
                <i class="bi bi-megaphone" style="font-size: 80px; color: #FF0000;"></i>
              </div>
              <h2><?= htmlspecialchars($job['job_title']) ?></h2>
              <p><?= nl2br(htmlspecialchars($job['job_description'])) ?></p>
              
              <div class="job-actions">
                <a href="#" class="btn-stima-red" data-bs-toggle="modal" data-bs-target="#jobModal<?= $job['job_id'] ?>">JOB REQUIREMENTS</a>
                <a href="mailto:hr@mashirikianosacco.co.ke?subject=<?= urlencode($job['job_title'] . ' Application') ?>" class="btn-stima-red">APPLY HERE</a>
              </div>
              
              <div class="job-deadline-text">
                DEADLINE: <?= strtoupper(date('d F Y', strtotime($job['job_deadline']))) ?>
              </div>
            </div>
          </div>

          <!-- Job Requirements Modal -->
          <div class="modal fade" id="jobModal<?= $job['job_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title fw-bold text-dark">Requirements: <?= htmlspecialchars($job['job_title']) ?></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-start text-dark">
                  <ul class="list-group list-group-flush">
                    <?php 
                    $requirements = explode("\n", $job['job_requirements']);
                    foreach ($requirements as $req) {
                      if (trim($req)) {
                        echo "<li class='list-group-item'><i class='bi bi-check2-circle text-success me-2'></i>" . htmlspecialchars(trim($req)) . "</li>";
                      }
                    }
                    ?>
                  </ul>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                  <a href="mailto:hr@mashirikianosacco.co.ke?subject=<?= urlencode($job['job_title'] . ' Application') ?>" class="btn btn-danger">Apply Now</a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

  </main>

  <!-- ======= Footer ======= -->
  <footer id="footer" class="footer">
    <div class="container">
      <div class="row gy-4">
        <div class="col-lg-5 col-md-12 footer-info">
          <a href="index.php" class="logo d-flex align-items-center">
            <span>Mashirikiano SACCO</span>
          </a>
          <p>For all your SACCO needs, connect with us on our social media channels. Our team is ready to support your savings, loans, and financial growth journey.</p>
          <div class="social-links d-flex mt-4">
            <a href="#" class="twitter"><i class="bi bi-twitter"></i></a>
            <a href="#" class="facebook"><i class="bi bi-facebook"></i></a>
            <a href="#" class="instagram"><i class="bi bi-instagram"></i></a>
            <a href="#" class="linkedin"><i class="bi bi-linkedin"></i></a>
          </div>
        </div>
        <div class="col-lg-2 col-6 footer-links">
          <h4>Useful Links</h4>
          <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="about.php">About us</a></li>
            <li><a href="loans.php">Services</a></li>
          </ul>
        </div>
        <div class="col-lg-3 col-md-12 footer-contact text-center text-md-start">
          <h4>Contact Us</h4>
          <p>Makongeni Centre, off Thika Garissa Road<br>Makongeni, Thika<br><br><strong>Phone:</strong> 0758500557<br><strong>Email:</strong> info@mashirikianosacco.co.ke<br></p>
        </div>
      </div>
    </div>
  </footer>

  <a href="#" class="scroll-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>
  <div id="preloader"></div>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/purecounter/purecounter_vanilla.js"></script>
  <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>
  <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script src="assets/js/main.js"></script>

</body>
</html>
