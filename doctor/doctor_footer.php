<style>
/* ==================================
   DOCTOR DASHBOARD FOOTER
   ================================== */

/* Main dashboard layout */
body:has(.doctor-footer) .main-content {
    display: flex !important;
    flex-direction: column !important;
    min-height: 100vh !important;
}

/* Dashboard content fills available space */
body:has(.doctor-footer) .content-area {
    display: flex !important;
    flex-direction: column !important;
    flex: 1 !important;
    min-height: 0 !important;
}

/* Footer stays at the bottom of content */
.doctor-footer {
    position: static !important;
    bottom: auto !important;
    left: auto !important;
    right: auto !important;

    background: linear-gradient(110deg, #05668d, #028090);
    color: #ffffff;

    width: 100%;
    box-sizing: border-box;

    padding: 35px 40px 15px;

    margin-top: auto !important;
    flex-shrink: 0 !important;
    clear: both;
}

/* Footer columns */
.doctor-footer-content {
    display: grid;
    grid-template-columns: 1.3fr 1fr 1fr;
    gap: 35px;
    align-items: start;
    width: 100%;
}

/* Logo */
.doctor-footer-logo {
    display: flex;
    align-items: center;
    gap: 15px;
}

.doctor-footer-logo img {
    width: 75px;
    height: 75px;
    object-fit: contain;
    background: white;
    padding: 5px;
}

/* Headings */
.doctor-footer h5 {
    font-size: 17px;
    font-weight: bold;
    color: #f0f3bd;
    margin-bottom: 15px;
}

/* Paragraphs */
.doctor-footer p {
    font-size: 13px;
    margin-bottom: 10px;
    line-height: 1.6;
}

/* Links */
.doctor-footer a {
    color: white;
    text-decoration: none;
}

.doctor-footer a:hover {
    text-decoration: underline;
}

/* Copyright centered */
.doctor-footer-bottom {
    display: block !important;
    width: 100% !important;
    box-sizing: border-box;

    border-top: 1px solid rgba(255,255,255,0.3);

    margin-top: 25px;
    padding-top: 12px;

    text-align: center !important;
    font-size: 12px;
    color: white;
}

/* Mobile layout */
@media (max-width: 768px) {
    .doctor-footer {
        padding: 25px 20px 15px;
    }

    .doctor-footer-content {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .doctor-footer-bottom {
        text-align: center !important;
    }
}
</style>

<footer class="doctor-footer">

    <div class="doctor-footer-content">

        <!-- SYSTEM LOGO -->
        <div class="doctor-footer-logo">

            <img
                src="/meditrack/assets/Logo.png"
                alt="MediTrack Logo"
            >

            <div>
                <h5>MediTrack</h5>
                <p>Electronic Medical Record System</p>
            </div>

        </div>

        <!-- OPERATION HOURS -->
        <div>

            <h5>MediTrack</h5>

            <p>
                <i class="bi bi-clock me-2"></i>
                Operation Hours:
            </p>

            <p>Wednesday - Monday: 24 hours</p>

            <p>
                <a href="/meditrack/index.php">
                    <i class="bi bi-house-door-fill me-2"></i>
                    Home
                </a>
            </p>

        </div>

        <!-- CONTACT US -->
        <div>

            <h5>Contact Us</h5>

            <p>
                <i class="bi bi-telephone-fill me-2"></i>
                Phone: +60 3-0000 0000
            </p>

            <p>
                <i class="bi bi-geo-alt-fill me-2"></i>
                Location: Malaysia
            </p>

            <p>
                <i class="bi bi-envelope-fill me-2"></i>
                Email: info@meditrack.com
            </p>

        </div>

    </div>

    <!-- COPYRIGHT -->
    <div class="doctor-footer-bottom">
        &copy; <?= date('Y') ?> MediTrack. All Rights Reserved.
    </div>

</footer>