</main>

    <?php if (basename($_SERVER['PHP_SELF']) === 'dashboard.php'): ?>
    <style>
        .meditrack-footer {
            background: linear-gradient(110deg, #05668d, #028090);
            color: white;
            width: 100%;
            box-sizing: border-box;
            padding: 35px 40px 15px;
            margin-top: auto;
        }

        .meditrack-footer-content {
            display: grid;
            grid-template-columns: 1.3fr 1fr 1fr;
            gap: 35px;
            align-items: start;
            width: 100%;
        }

        .meditrack-footer-logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .meditrack-footer-logo img {
            width: 75px;
            height: 75px;
            object-fit: contain;
            background: white;
            padding: 5px;
            border-radius: 8px;
        }

        .meditrack-footer h5 {
            font-size: 17px;
            font-weight: bold;
            color: #f0f3bd;
            margin-bottom: 15px;
        }

        .meditrack-footer p {
            font-size: 13px;
            margin-bottom: 10px;
            line-height: 1.6;
            color: #ffffff;
        }

        .meditrack-footer a {
            color: white;
            text-decoration: none;
        }

        .meditrack-footer a:hover {
            text-decoration: underline;
        }

        .meditrack-footer-bottom {
            display: block;
            width: 100%;
            border-top: 1px solid rgba(255,255,255,0.3);
            margin-top: 25px;
            padding-top: 12px;
            text-align: center;
            font-size: 12px;
            color: white;
        }

        @media (max-width: 768px) {
            .meditrack-footer {
                padding: 25px 20px 15px;
            }

            .meditrack-footer-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
    </style>

    <footer class="meditrack-footer">
        <div class="meditrack-footer-content">
            <!-- LOGO -->
            <div class="meditrack-footer-logo">
                <img src="/meditrack/assets/Logo.png" alt="MediTrack Logo">
                <div>
                    <h5>MediTrack</h5>
                    <p class="mb-0">Electronic Medical Record System</p>
                </div>
            </div>

            <!-- OPERATION HOURS -->
            <div>
                <h5>MediTrack</h5>
                <p><i class="bi bi-clock me-2"></i>Operation Hours:</p>
                <p>Wednesday - Monday: 24 hours</p>
                <p class="mb-0">
                    <a href="dashboard.php">
                        <i class="bi bi-house-door-fill me-2"></i>Home
                    </a>
                </p>
            </div>

            <!-- CONTACT US -->
            <div>
                <h5>Contact Us</h5>
                <p><i class="bi bi-telephone-fill me-2"></i>Phone: +60 3-0000 0000</p>
                <p><i class="bi bi-geo-alt-fill me-2"></i>Location: Malaysia</p>
                <p class="mb-0"><i class="bi bi-envelope-fill me-2"></i>Email: info@meditrack.com</p>
            </div>
        </div>

        <!-- COPYRIGHT -->
        <div class="meditrack-footer-bottom">
            &copy; <?= date('Y') ?> MediTrack. All Rights Reserved.
        </div>
    </footer>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>