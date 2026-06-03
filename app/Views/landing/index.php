<?php
use App\Core\View;
$layout = 'landing';
$year = date('Y');
?>
<header class="lp-nav" id="top">
  <div class="lp-container lp-nav__inner">
    <a class="lp-logo" href="<?= $base ?>/">
      <span class="lp-logo__mark"><i class="bi bi-mortarboard-fill"></i></span>
      <span class="lp-logo__text">SSDA<span class="lp-logo__accent">CMIS</span></span>
    </a>
    <button type="button" class="lp-nav__toggle" id="lpNavToggle" aria-label="Open menu" aria-expanded="false">
      <i class="bi bi-list"></i>
    </button>
    <nav class="lp-nav__links" id="lpNavLinks" aria-label="Primary">
      <a href="#features">Features</a>
      <a href="#modules">Modules</a>
      <a href="#preview">Preview</a>
      <a href="#why">Why Us</a>
      <a href="#about">About</a>
      <a href="#contact">Contact</a>
      <a class="lp-btn lp-btn--primary lp-btn--sm" href="<?= $base ?>/login">Sign In</a>
    </nav>
  </div>
</header>

<main>
  <section class="lp-hero">
    <div class="lp-hero__bg" aria-hidden="true"></div>
    <div class="lp-container lp-hero__grid">
      <div class="lp-hero__copy reveal">
        <p class="lp-eyebrow">Academic Management Portal</p>
        <h1 class="lp-hero__title">Transform Academic Management with <span class="lp-gradient-text">SSDACMIS</span></h1>
        <p class="lp-hero__sub">
          A powerful Student Management Information System developed by <strong>SSD IT Solutions</strong>
          that helps schools, colleges, and universities manage enrollment, academics, examinations,
          finances, and reporting from one intelligent platform.
        </p>
        <ul class="lp-hero__checks">
          <li><i class="bi bi-check2-circle"></i> Student Records Management</li>
          <li><i class="bi bi-check2-circle"></i> Examination &amp; Results Processing</li>
          <li><i class="bi bi-check2-circle"></i> HOD Approval Workflows</li>
          <li><i class="bi bi-check2-circle"></i> Automated Report Cards</li>
          <li><i class="bi bi-check2-circle"></i> Financial Management</li>
          <li><i class="bi bi-check2-circle"></i> Multi-Role Access Control</li>
        </ul>
        <div class="lp-hero__cta">
          <a class="lp-btn lp-btn--primary" href="#contact">Request a Demo</a>
          <a class="lp-btn lp-btn--glass" href="#preview"><i class="bi bi-play-circle"></i> Watch Live Demo</a>
        </div>
      </div>
      <?php include __DIR__ . '/_hero_mockup.php'; ?>
    </div>
  </section>

  <section class="lp-trust reveal" id="trust">
    <div class="lp-container">
      <p class="lp-section-eyebrow">Trusted by Modern Educational Institutions</p>
      <div class="lp-trust__logos">
        <span class="lp-trust__logo"><i class="bi bi-building"></i> Green Valley Academy</span>
        <span class="lp-trust__logo"><i class="bi bi-building"></i> Lakeside College</span>
        <span class="lp-trust__logo"><i class="bi bi-building"></i> Summit High School</span>
        <span class="lp-trust__logo"><i class="bi bi-building"></i> Horizon Institute</span>
        <span class="lp-trust__logo"><i class="bi bi-building"></i> Unity Campus</span>
      </div>
      <div class="lp-trust__stats">
        <div class="lp-stat-card"><span class="lp-stat-card__num" data-count="15000">15,000+</span><span class="lp-stat-card__lbl">Students Managed</span></div>
        <div class="lp-stat-card"><span class="lp-stat-card__num" data-count="1200">1,200+</span><span class="lp-stat-card__lbl">Academic Staff</span></div>
        <div class="lp-stat-card"><span class="lp-stat-card__num" data-count="150">150+</span><span class="lp-stat-card__lbl">Institutions</span></div>
        <div class="lp-stat-card"><span class="lp-stat-card__num">99.9%</span><span class="lp-stat-card__lbl">System Uptime</span></div>
      </div>
    </div>
  </section>

  <section class="lp-section" id="features">
    <div class="lp-container">
      <div class="lp-section__head reveal">
        <p class="lp-section-eyebrow">Platform Capabilities</p>
        <h2 class="lp-section__title">Everything Your Institution Needs</h2>
      </div>
      <div class="lp-features">
        <?php
        $features = [
          ['bi-people-fill', 'Student Management', 'Manage student registration, profiles, classes, streams, and enrollment records from one centralized platform.', '#2563EB'],
          ['bi-journal-bookmark-fill', 'Academic Administration', 'Manage subjects, timetables, attendance, grading systems, and academic records.', '#7C3AED'],
          ['bi-clipboard-data', 'Examination Management', 'Efficient mark entry, grading, moderation, and results processing workflows.', '#0891B2'],
          ['bi-patch-check-fill', 'HOD Approval Workflows', 'Built-in verification and approval processes for academic transparency and accountability.', '#10B981'],
          ['bi-cash-stack', 'Financial Management', 'Track fees, invoices, balances, payments, and financial reports in real time.', '#D97706'],
          ['bi-bar-chart-line-fill', 'Reports & Analytics', 'Generate performance reports, dashboards, report cards, and institutional insights instantly.', '#E11D48'],
        ];
        foreach ($features as $f): ?>
        <article class="lp-feature-card reveal" style="--feat-accent: <?= $f[3] ?>">
          <div class="lp-feature-card__icon"><i class="bi <?= $f[0] ?>"></i></div>
          <h3><?= $f[1] ?></h3>
          <p><?= $f[2] ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="lp-section lp-section--alt" id="modules">
    <div class="lp-container">
      <div class="lp-section__head reveal">
        <p class="lp-section-eyebrow">Complete Suite</p>
        <h2 class="lp-section__title">System Modules</h2>
        <p class="lp-section__sub">Sixteen integrated modules powering every department across your institution.</p>
      </div>
      <div class="lp-modules reveal">
        <?php
        $mods = [
          ['bi-person-plus-fill', 'Admissions', '#2563EB'],
          ['bi-folder-fill', 'Student Records', '#3B82F6'],
          ['bi-calendar-check-fill', 'Attendance Management', '#10B981'],
          ['bi-book-fill', 'Academic Management', '#8B5CF6'],
          ['bi-calendar-week-fill', 'Timetable Management', '#06B6D4'],
          ['bi-pencil-square', 'Examination System', '#F59E0B'],
          ['bi-graph-up', 'Results Processing', '#EC4899'],
          ['bi-file-earmark-text-fill', 'Report Cards', '#14B8A6'],
          ['bi-wallet2', 'Fee Management', '#F97316'],
          ['bi-person-badge-fill', 'Staff Management', '#6366F1'],
          ['bi-house-heart-fill', 'Parent Portal', '#22C55E'],
          ['bi-mortarboard-fill', 'Student Portal', '#0EA5E9'],
          ['bi-bell-fill', 'Notifications', '#EF4444'],
          ['bi-speedometer2', 'Analytics Dashboard', '#2563EB'],
          ['bi-file-earmark-lock-fill', 'Document Management', '#64748B'],
          ['bi-chat-dots-fill', 'Communication Center', '#38BDF8'],
        ];
        foreach ($mods as $m): ?>
        <div class="lp-module" style="--mod: <?= $m[2] ?>">
          <i class="bi <?= $m[0] ?>"></i>
          <span><?= $m[1] ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="lp-section" id="preview">
    <div class="lp-container">
      <div class="lp-section__head reveal">
        <p class="lp-section-eyebrow">Product Tour</p>
        <h2 class="lp-section__title">Experience SSDACMIS in Action</h2>
      </div>
      <div class="lp-preview-grid reveal">
        <?php
        $previews = [
          ['Admin Dashboard', 'Student Overview · Revenue Analytics · Attendance · Exam Statistics', ['bi-speedometer2', 'bi-currency-dollar', 'bi-calendar-check', 'bi-bar-chart']],
          ['Teacher Dashboard', 'Class Lists · Mark Entry · Attendance · Subject Allocation', ['bi-people', 'bi-pencil', 'bi-check2-square', 'bi-grid']],
          ['Student Dashboard', 'Results · Timetable · Attendance · Fee Balance', ['bi-trophy', 'bi-calendar3', 'bi-clipboard-check', 'bi-wallet']],
          ['Bursar Dashboard', 'Payments · Outstanding Balances · Financial Reports', ['bi-receipt', 'bi-graph-down', 'bi-file-earmark-spreadsheet', 'bi-shield-check']],
        ];
        foreach ($previews as $i => $p): ?>
        <article class="lp-preview-card" style="--delay: <?= $i * 0.08 ?>s">
          <div class="lp-preview-card__device">
            <div class="lp-preview-card__screen">
              <div class="lp-preview-card__mini-head"></div>
              <div class="lp-preview-card__mini-body">
                <?php foreach ($p[2] as $ic): ?><span><i class="bi <?= $ic ?>"></i></span><?php endforeach; ?>
              </div>
            </div>
          </div>
          <h3><?= $p[0] ?></h3>
          <p><?= $p[1] ?></p>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="lp-section lp-section--alt" id="why">
    <div class="lp-container lp-why">
      <div class="lp-section__head reveal">
        <p class="lp-section-eyebrow">Competitive Edge</p>
        <h2 class="lp-section__title">Why Educational Institutions Choose SSDACMIS</h2>
      </div>
      <ul class="lp-why__list reveal">
        <?php
        $benefits = [
          'Faster Administrative Processes', 'Improved Academic Monitoring', 'Secure Cloud-Based Access',
          'Real-Time Reporting', 'Automated Workflows', 'Better Data Accuracy', 'Financial Transparency',
          'Role-Based Security', 'Reduced Paperwork', 'Enhanced Decision Making',
        ];
        foreach ($benefits as $b): ?>
        <li><i class="bi bi-check-lg"></i><?= $b ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <section class="lp-section" id="about">
    <div class="lp-container lp-about reveal">
      <div class="lp-about__copy">
        <p class="lp-section-eyebrow">Our Company</p>
        <h2 class="lp-section__title">Powered by SSD IT Solutions</h2>
        <p class="lp-about__text">
          SSD IT Solutions is a leading technology company specializing in Information Systems,
          Enterprise Software Development, Digital Transformation, Cloud Solutions, and Educational
          Technology Platforms.
        </p>
        <div class="lp-about__metrics">
          <div><strong>10+</strong><span>Years of Experience</span></div>
          <div><strong>200+</strong><span>Projects Delivered</span></div>
          <div><strong>24/7</strong><span>Support Availability</span></div>
        </div>
      </div>
      <div class="lp-about__visual" aria-hidden="true">
        <div class="lp-about__glass">
          <i class="bi bi-cpu-fill"></i>
          <span>Enterprise EdTech</span>
        </div>
      </div>
    </div>
  </section>

  <section class="lp-section lp-section--alt" id="testimonials">
    <div class="lp-container">
      <div class="lp-section__head reveal">
        <p class="lp-section-eyebrow">Voices from the Field</p>
        <h2 class="lp-section__title">What Leaders Say</h2>
      </div>
      <div class="lp-testimonials reveal">
        <?php
        $quotes = [
          ['School Administrator', 'SSDACMIS has completely transformed our academic administration.', 'bi-building'],
          ['Head of Department', 'The approval workflows have significantly improved accountability and efficiency.', 'bi-mortarboard'],
          ['Bursar', 'The financial management module provides unmatched visibility into school finances.', 'bi-cash-coin'],
          ['Teacher', 'Mark entry and report generation are now effortless.', 'bi-person-workspace'],
        ];
        foreach ($quotes as $q): ?>
        <blockquote class="lp-testimonial">
          <i class="bi <?= $q[2] ?> lp-testimonial__icon"></i>
          <p>&ldquo;<?= $q[1] ?>&rdquo;</p>
          <footer>&mdash; <?= $q[0] ?></footer>
        </blockquote>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="lp-cta" id="contact">
    <div class="lp-container lp-cta__inner reveal">
      <h2>Ready to Digitize Your Institution?</h2>
      <p>Join institutions already transforming education with SSDACMIS.</p>
      <div class="lp-cta__actions">
        <a class="lp-btn lp-btn--white" href="mailto:info@ssd-it.local?subject=SSDACMIS%20Demo%20Request">Request Demo</a>
        <a class="lp-btn lp-btn--outline-white" href="mailto:sales@ssd-it.local?subject=SSDACMIS%20Sales">Contact Sales</a>
        <a class="lp-btn lp-btn--outline-white" href="<?= $base ?>/login"><i class="bi bi-box-arrow-in-right"></i> Sign In to Portal</a>
      </div>
    </div>
  </section>
</main>

<footer class="lp-footer">
  <div class="lp-container lp-footer__grid">
    <div class="lp-footer__brand">
      <div class="lp-logo lp-logo--footer">
        <span class="lp-logo__mark"><i class="bi bi-code-slash"></i></span>
        <span class="lp-logo__text">SSD IT Solutions</span>
      </div>
      <p class="lp-footer__product">Product: <strong>SSDACMIS</strong></p>
      <div class="lp-footer__social">
        <a href="#" aria-label="LinkedIn"><i class="bi bi-linkedin"></i></a>
        <a href="#" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
        <a href="#" aria-label="X"><i class="bi bi-twitter-x"></i></a>
        <a href="#" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
      </div>
    </div>
    <nav class="lp-footer__links" aria-label="Footer">
      <a href="<?= $base ?>/">Home</a>
      <a href="#features">Features</a>
      <a href="#modules">Solutions</a>
      <a href="#contact">Pricing</a>
      <a href="#preview">Documentation</a>
      <a href="#contact">Contact</a>
      <a href="mailto:support@ssd-it.local">Support</a>
      <a href="#">Privacy Policy</a>
      <a href="#">Terms of Service</a>
    </nav>
  </div>
  <div class="lp-container lp-footer__copy">
    <p>&copy; <?= (int) $year ?> SSD IT Solutions. All Rights Reserved.</p>
  </div>
</footer>
