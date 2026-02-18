package com.example.pidev.controller.event;

import com.example.pidev.HelloController;
import com.example.pidev.model.event.Event;
import com.example.pidev.model.event.EventCategory;
import com.example.pidev.service.event.EventCategoryService;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.fxml.FXML;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.util.Duration;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.Locale;

/**
 * Controller pour la consultation détaillée d'un événement
 * @author Ons Abdesslem
 */
public class EventViewController {

    // ==================== FXML ELEMENTS ====================

    // En-tête (sans ID)
    @FXML private Label titleHeaderLabel;
    @FXML private Label statusBadge;

    // Informations principales
    @FXML private Label detailTitleLabel;
    @FXML private Label startDateLabel;
    @FXML private Label endDateLabel;
    @FXML private Label locationLabel;
    @FXML private Label capacityLabel;
    @FXML private Label categoryLabel;
    @FXML private Label priceLabel;

    // Description
    @FXML private Label descriptionLabel;

    // Métadonnées
    @FXML private Label createdByLabel;
    @FXML private Label updatedByLabel;

    // Boutons
    @FXML private Button backBtn;

    // ==================== NAVBAR ELEMENTS ====================
    @FXML private Label dateLabel;
    @FXML private Label timeLabel;

    // ==================== SERVICES ====================

    private HelloController helloController;
    private EventCategoryService categoryService;
    private Event currentEvent;

    // ==================== INITIALIZATION ====================

    @FXML
    public void initialize() {
        System.out.println("✅ EventViewController initialisé");
        categoryService = new EventCategoryService();

        // Initialiser la date et l'heure
        updateDateTime();

        // Mettre à jour l'heure chaque seconde
        Timeline clock = new Timeline(
                new KeyFrame(Duration.ZERO, e -> updateDateTime()),
                new KeyFrame(Duration.seconds(1))
        );
        clock.setCycleCount(Timeline.INDEFINITE);
        clock.play();
    }

    private void updateDateTime() {
        LocalDateTime now = LocalDateTime.now();
        DateTimeFormatter dateFormatter = DateTimeFormatter.ofPattern("EEEE dd MMMM yyyy", Locale.FRENCH);
        DateTimeFormatter timeFormatter = DateTimeFormatter.ofPattern("HH:mm:ss");

        if (dateLabel != null) {
            String dateText = now.format(dateFormatter);
            dateLabel.setText(dateText.substring(0, 1).toUpperCase() + dateText.substring(1));
        }
        if (timeLabel != null) {
            timeLabel.setText(now.format(timeFormatter));
        }
    }

    public void setHelloController(HelloController helloController) {
        this.helloController = helloController;
    }

    public void setEvent(Event event) {
        this.currentEvent = event;

        if (event == null) {
            System.err.println("❌ Événement null passé à EventViewController");
            return;
        }

        // Vérifier si les labels sont injectés
        if (titleHeaderLabel == null) {
            System.out.println("⚠️ Labels pas encore injectés, retry...");
            javafx.application.Platform.runLater(() -> setEvent(event));
            return;
        }

        displayEvent(event);
    }

    private String getCategoryName(int categoryId) {
        try {
            EventCategory cat = categoryService.getCategoryById(categoryId);
            return cat != null ? cat.getName() : "Catégorie " + categoryId;
        } catch (Exception e) {
            System.err.println("❌ Erreur récupération catégorie: " + e.getMessage());
            return "Catégorie " + categoryId;
        }
    }

    private void displayEvent(Event event) {
        System.out.println("\n📋 === AFFICHAGE ÉVÉNEMENT: " + event.getTitle() + " ===");

        // En-tête (sans ID)
        titleHeaderLabel.setText(event.getTitle());

        // Statut avec badge (basé sur la date)
        LocalDateTime now = LocalDateTime.now();
        String status;
        String statusStyle;

        if (event.getEndDate() == null || event.getEndDate().isBefore(now)) {
            status = "Terminé";
            statusStyle = "-fx-background-color: #e5e7eb; -fx-text-fill: #4b5563;";
        } else if (event.getStartDate() != null && event.getStartDate().isAfter(now)) {
            status = "À venir";
            statusStyle = "-fx-background-color: #d1fae5; -fx-text-fill: #065f46;";
        } else if (event.getStartDate() != null && event.getEndDate() != null &&
                now.isAfter(event.getStartDate()) && now.isBefore(event.getEndDate())) {
            status = "En cours";
            statusStyle = "-fx-background-color: #fef3c7; -fx-text-fill: #92400e;";
        } else {
            status = "Terminé";
            statusStyle = "-fx-background-color: #e5e7eb; -fx-text-fill: #4b5563;";
        }

        statusBadge.setText(status);
        statusBadge.setStyle(statusStyle + " -fx-padding: 4 12; -fx-background-radius: 20; -fx-font-size: 13px; -fx-font-weight: 600;");

        // Informations principales
        detailTitleLabel.setText(event.getTitle());

        // Dates
        DateTimeFormatter dateTimeFormatter = DateTimeFormatter.ofPattern("dd/MM/yyyy HH:mm");
        if (event.getStartDate() != null) {
            startDateLabel.setText(event.getStartDate().format(dateTimeFormatter));
        } else {
            startDateLabel.setText("Non définie");
        }

        if (event.getEndDate() != null) {
            endDateLabel.setText(event.getEndDate().format(dateTimeFormatter));
        } else {
            endDateLabel.setText("Non définie");
        }

        locationLabel.setText(event.getLocation() != null ? event.getLocation() : "Non spécifié");
        capacityLabel.setText(String.valueOf(event.getCapacity()));
        categoryLabel.setText(getCategoryName(event.getCategoryId()));
        priceLabel.setText(event.getPriceDisplay());

        // Description
        if (event.getDescription() != null && !event.getDescription().isEmpty()) {
            descriptionLabel.setText(event.getDescription());
        } else {
            descriptionLabel.setText("Aucune description fournie pour cet événement.");
            descriptionLabel.setStyle("-fx-text-fill: #94a3b8; -fx-font-style: italic;");
        }

        // Métadonnées
        DateTimeFormatter fullFormatter = DateTimeFormatter.ofPattern("dd/MM/yyyy HH:mm");

        if (event.getCreatedAt() != null) {
            createdByLabel.setText("Créé le: " + event.getCreatedAt().format(fullFormatter));
        } else {
            createdByLabel.setText("Créé le: inconnue");
        }

        if (event.getUpdatedAt() != null) {
            updatedByLabel.setText("Modifié le: " + event.getUpdatedAt().format(fullFormatter));
        } else {
            updatedByLabel.setText("Modifié le: jamais");
        }

        System.out.println("✅ Événement affiché: " + event.getTitle());
    }

    // ==================== ACTIONS ====================

    @FXML
    private void handleBack() {
        if (helloController != null) {
            helloController.showEventsList();
        }
    }
}