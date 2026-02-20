package com.example.pidev.controller.front;

import com.example.pidev.HelloApplication;
import com.example.pidev.model.event.Event;
import com.example.pidev.model.event.EventCategory;
import com.example.pidev.service.event.EventCategoryService;
import com.example.pidev.utils.UserSession;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.scene.layout.StackPane;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.Locale;

/**
 * Contrôleur pour la page de détails d'un événement (front office)
 * @author Ons Abdesslem
 */
public class EventDetailController {

    @FXML private StackPane heroImage;
    @FXML private Label titleLabel;
    @FXML private Label categoryBadge;
    @FXML private Label statusBadge;
    @FXML private Label priceLabel;
    @FXML private Label capacityLabel;
    @FXML private Label dateLabel;
    @FXML private Label durationLabel;
    @FXML private Label locationLabel;
    @FXML private Label descriptionLabel;
    @FXML private Label organizerLabel;
    @FXML private Label capacityInfoLabel;

    private Event currentEvent;
    private EventCategoryService categoryService;

    @FXML
    public void initialize() {
        System.out.println("✅ EventDetailController initialisé");
        categoryService = new EventCategoryService();
    }

    /**
     * Définit l'événement à afficher
     */
    public void setEvent(Event event) {
        if (event == null) {
            System.err.println("❌ Événement null");
            return;
        }

        this.currentEvent = event;
        displayEventDetails();
    }

    /**
     * Affiche les détails de l'événement
     */
    private void displayEventDetails() {
        // Titre
        titleLabel.setText(currentEvent.getTitle());

        // Catégorie
        String categoryName = getCategoryName(currentEvent.getCategoryId());
        categoryBadge.setText(categoryName);

        // Statut
        LocalDateTime now = LocalDateTime.now();
        String status;
        String statusStyle;

        if (currentEvent.getStartDate().isAfter(now)) {
            status = "À venir";
            statusStyle = "-fx-background-color: #d1fae5; -fx-text-fill: #065f46; -fx-padding: 8 20; " +
                         "-fx-background-radius: 20; -fx-font-size: 14px; -fx-font-weight: bold;";
        } else if (currentEvent.getEndDate().isAfter(now)) {
            status = "En cours";
            statusStyle = "-fx-background-color: #fef3c7; -fx-text-fill: #92400e; -fx-padding: 8 20; " +
                         "-fx-background-radius: 20; -fx-font-size: 14px; -fx-font-weight: bold;";
        } else {
            status = "Terminé";
            statusStyle = "-fx-background-color: #e5e7eb; -fx-text-fill: #4b5563; -fx-padding: 8 20; " +
                         "-fx-background-radius: 20; -fx-font-size: 14px; -fx-font-weight: bold;";
        }
        statusBadge.setText(status);
        statusBadge.setStyle(statusStyle);

        // Prix
        if (currentEvent.isFree()) {
            priceLabel.setText("Gratuit");
            priceLabel.setStyle("-fx-font-size: 36px; -fx-font-weight: bold; -fx-text-fill: #10b981;");
        } else {
            priceLabel.setText(String.format("%.2f DT", currentEvent.getTicketPrice()));
            priceLabel.setStyle("-fx-font-size: 36px; -fx-font-weight: bold; -fx-text-fill: #0D47A1;");
        }

        // Capacité
        capacityLabel.setText(currentEvent.getCapacity() + " places disponibles");
        capacityInfoLabel.setText(currentEvent.getCapacity() + " personnes");

        // Date
        DateTimeFormatter dateFormatter = DateTimeFormatter.ofPattern("EEEE dd MMMM yyyy 'à' HH:mm", Locale.FRENCH);
        String formattedDate = currentEvent.getStartDate().format(dateFormatter);
        formattedDate = formattedDate.substring(0, 1).toUpperCase() + formattedDate.substring(1);
        dateLabel.setText(formattedDate);

        // Durée
        long hours = (long) currentEvent.getDurationInHours();
        if (hours < 1) {
            durationLabel.setText("Moins d'1 heure");
        } else if (hours == 1) {
            durationLabel.setText("1 heure");
        } else if (hours < 24) {
            durationLabel.setText(hours + " heures");
        } else {
            long days = hours / 24;
            durationLabel.setText(days + " jour" + (days > 1 ? "s" : ""));
        }

        // Lieu
        locationLabel.setText(currentEvent.getLocation() != null ? currentEvent.getLocation() : "Lieu non spécifié");

        // Description
        descriptionLabel.setText(currentEvent.getDescription() != null ? currentEvent.getDescription() : "Pas de description disponible.");

        // Organisateur (par défaut)
        organizerLabel.setText("EventFlow");

        // Image hero
        if (currentEvent.getImageUrl() != null && !currentEvent.getImageUrl().isEmpty()) {
            try {
                ImageView imageView = new ImageView(new Image(currentEvent.getImageUrl()));
                imageView.setFitWidth(1400);
                imageView.setFitHeight(400);
                imageView.setPreserveRatio(false);
                heroImage.getChildren().clear();
                heroImage.getChildren().add(imageView);
            } catch (Exception e) {
                System.err.println("❌ Erreur chargement image: " + e.getMessage());
                setDefaultHeroImage();
            }
        } else {
            setDefaultHeroImage();
        }
    }

    /**
     * Définit l'image hero par défaut
     */
    private void setDefaultHeroImage() {
        Label placeholder = new Label("📅");
        placeholder.setStyle("-fx-font-size: 100px;");
        heroImage.getChildren().clear();
        heroImage.getChildren().add(placeholder);
    }

    /**
     * Retourne le nom de la catégorie
     */
    private String getCategoryName(int categoryId) {
        try {
            EventCategory category = categoryService.getCategoryById(categoryId);
            return category != null ? category.getName() : "Autre";
        } catch (Exception e) {
            return "Autre";
        }
    }

    @FXML
    private void handleParticipate() {
        if (currentEvent == null) return;

        System.out.println("🎫 Participer à: " + currentEvent.getTitle());

        // Vérifier si l'utilisateur est connecté
        UserSession session = UserSession.getInstance();
        if (session.getCurrentUser() == null) {
            // Sauvegarder l'ID de l'événement pour y participer après connexion
            session.setPendingEventId(currentEvent.getId());

            // Rediriger vers la page de connexion
            Alert alert = new Alert(Alert.AlertType.INFORMATION);
            alert.setTitle("Connexion requise");
            alert.setHeaderText("Vous devez être connecté");
            alert.setContentText("Pour participer à cet événement, veuillez vous connecter ou créer un compte.\n\n" +
                               "Après connexion, votre participation sera automatiquement enregistrée.");

            ButtonType loginBtn = new ButtonType("Se connecter");
            ButtonType signupBtn = new ButtonType("S'inscrire");
            ButtonType cancelBtn = new ButtonType("Annuler", ButtonBar.ButtonData.CANCEL_CLOSE);

            alert.getButtonTypes().setAll(loginBtn, signupBtn, cancelBtn);

            alert.showAndWait().ifPresent(response -> {
                if (response == loginBtn) {
                    HelloApplication.loadLoginPage();
                } else if (response == signupBtn) {
                    HelloApplication.loadSignupPage();
                } else {
                    // Annuler : nettoyer le pendingEventId
                    session.clearPendingEventId();
                }
            });
            return;
        }

        // Utilisateur connecté : créer un ticket immédiatement
        createTicketForEvent(currentEvent.getId(), currentEvent.getTitle());
    }

    /**
     * Crée un ticket pour un événement
     */
    private void createTicketForEvent(int eventId, String eventTitle) {
        try {
            com.example.pidev.service.event.EventTicketService ticketService =
                new com.example.pidev.service.event.EventTicketService();

            int userId = UserSession.getInstance().getCurrentUser().getId_User();

            // Créer le ticket dans la base de données
            com.example.pidev.model.event.EventTicket ticket = ticketService.createTicket(eventId, userId);

            if (ticket != null) {
                // Succès : afficher le ticket généré
                Alert alert = new Alert(Alert.AlertType.INFORMATION);
                alert.setTitle("✅ Participation confirmée");
                alert.setHeaderText("Vous participez à l'événement !");
                alert.setContentText(
                    "Événement : " + eventTitle + "\n\n" +
                    "Votre ticket : " + ticket.getTicketCode() + "\n\n" +
                    "Un email de confirmation vous sera envoyé.\n" +
                    "Conservez votre code de ticket pour accéder à l'événement."
                );

                // Style personnalisé
                alert.getDialogPane().setStyle(
                    "-fx-background-color: white; " +
                    "-fx-font-size: 14px;"
                );

                alert.showAndWait();

                System.out.println("✅ Ticket créé avec succès: " + ticket.getTicketCode());

            } else {
                // Erreur de création
                Alert alert = new Alert(Alert.AlertType.ERROR);
                alert.setTitle("Erreur");
                alert.setHeaderText("Impossible de créer le ticket");
                alert.setContentText(
                    "Une erreur est survenue lors de la création de votre ticket.\n" +
                    "Veuillez réessayer plus tard ou contacter le support."
                );
                alert.showAndWait();

                System.err.println("❌ Échec de la création du ticket");
            }

        } catch (Exception e) {
            System.err.println("❌ Erreur lors de la participation: " + e.getMessage());
            e.printStackTrace();

            Alert alert = new Alert(Alert.AlertType.ERROR);
            alert.setTitle("Erreur");
            alert.setHeaderText("Une erreur est survenue");
            alert.setContentText("Impossible de traiter votre demande. Veuillez réessayer.");
            alert.showAndWait();
        }
    }

    @FXML
    private void handleGoToHome() {
        HelloApplication.loadLandingPage();
    }

    @FXML
    private void handleGoToEvents() {
        HelloApplication.loadPublicEventsPage();
    }

    @FXML
    private void handleLogin() {
        HelloApplication.loadLoginPage();
    }

    @FXML
    private void handleSignup() {
        HelloApplication.loadSignupPage();
    }
}


