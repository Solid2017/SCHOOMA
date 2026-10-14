<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle       = "Demande d'admission | " . SITE_NAME;
$pageDescription = "Deposez votre demande d'admission en ligne et rejoignez notre etablissement des la prochaine annee universitaire.";
$programPreset   = sanitize($_GET['programme'] ?? '');

require_once __DIR__ . '/includes/header.php';
?>

<section class="page-banner">
    <div class="container">
        <h1>Demande d'admission</h1>
        <p class="breadcrumb"><a href="<?= SITE_URL ?>/index.php">Accueil</a> / Admissions</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="split">
            <div data-aos="fade-right">
                <span class="eyebrow">Rejoignez-nous</span>
                <h2>Commencez votre parcours des aujourd'hui</h2>
                <p style="color:var(--gray-700);margin:16px 0 24px;">Remplissez ce formulaire pour deposer une demande d'admission. Notre equipe des admissions vous recontactera rapidement pour finaliser votre inscription.</p>
                <ul class="about-list">
                    <li><i class="fa-solid fa-circle-check"></i> Reponse sous 48h ouvrees</li>
                    <li><i class="fa-solid fa-circle-check"></i> Accompagnement personnalise</li>
                    <li><i class="fa-solid fa-circle-check"></i> Paiement de la scolarite par tranches</li>
                </ul>
            </div>

            <div data-aos="fade-left">
                <div class="form-card">
                    <form id="admissionForm" novalidate>
                        <div class="grid grid-2" style="gap:16px;">
                            <div class="form-group">
                                <label for="first_name">Prenom *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Nom *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Telephone *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                        </div>
                        <div class="form-group">
                            <label for="program_wanted">Programme souhaite</label>
                            <input type="text" class="form-control" id="program_wanted" name="program_wanted" value="<?= $programPreset ?>">
                        </div>
                        <div class="form-group">
                            <label for="message">Message (optionnel)</label>
                            <textarea class="form-control" id="message" name="message" rows="4"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Envoyer ma demande <i class="fa-solid fa-paper-plane"></i></button>
                        <p class="form-feedback" id="admissionFeedback" role="status"></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
