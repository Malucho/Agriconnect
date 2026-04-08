<?php
session_start();
include_once 'includes/config.php';
include_once 'includes/functions.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $to = "support@agriconnect.com";
        $email_subject = "Contact Form: $subject";
        $email_body = "
            <h2>Contact Request</h2>
            <p><strong>Name:</strong> $name</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Subject:</strong> $subject</p>
            <p><strong>Message:</strong></p>
            <p>$message</p>
        ";

        if (sendEmail($to, $email_subject, $email_body)) {
            $success = "Your message has been sent successfully! We will get back to you soon.";
        } else {
            $error = "Failed to send message. Please try again later.";
        }
    }
}
$page_title = 'Contact Us';
include_once 'includes/head.php';
include_once 'includes/header.php';
?>
    
    <main>
        <section class="contact-section">
            <div class="container">
                <div class="section-title">
                    <h2>Get in Touch</h2>
                    <p>We're here to help and answer any question you might have.</p>
                </div>

                <div class="contact-grid">
                    <div class="contact-info-card">
                        <h3>Our Contact Details</h3>
                        <p><i class="fas fa-map-marker-alt"></i> 123 Agriconnect Lane, Nairobi, Kenya</p>
                        <p><i class="fas fa-phone-alt"></i> +254 712 345 678</p>
                        <p><i class="fas fa-envelope"></i> support@agriconnect.com</p>
                        <p><i class="fas fa-clock"></i> Mon - Fri: 9:00 AM - 5:00 PM</p>
                        <div class="social-icons">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>

                    <div class="contact-form-card">
                        <h3>Send Us a Message</h3>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form action="contact.php" method="post" class="contact-form">
                            <div class="form-group">
                                <label for="name">Your Name</label>
                                <input type="text" name="name" id="name" class="form-control" required value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Your Email</label>
                                <input type="email" name="email" id="email" class="form-control" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="subject">Subject</label>
                                <input type="text" name="subject" id="subject" class="form-control" required value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="message">Message</label>
                                <textarea name="message" id="message" rows="5" class="form-control" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Send Message</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>
    
    <?php include_once 'includes/footer.php'; ?>