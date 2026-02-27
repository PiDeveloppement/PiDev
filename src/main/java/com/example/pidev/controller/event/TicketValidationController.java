package com.example.pidev.controller.event;

import com.example.pidev.model.event.Event;
import com.example.pidev.model.event.EventTicket;
import com.example.pidev.service.event.EventService;
import com.example.pidev.service.event.EventTicketService;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.fxml.FXML;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.TextField;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;
import javafx.util.Duration;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.List;
import java.util.Locale;

/**
 * Contrôleur pour la validation rapide des billets à l'entrée
 * @author Ons Abdesslem
 */
public class TicketValidationController {

    @FXML private Label dateLabel;
    @FXML private Label timeLabel;
    @FXML private TextField ticketCodeField;
    @FXML private Button validateBtn;
    @FXML private Button resetBtn;

    @FXML private VBox resultBox;
    @FXML private Label resultIcon;
    @FXML private Label resultTitle;
    @FXML private Label resultMessage;

    @FXML private VBox ticketDetailsBox;
    @FXML private Label detailCodeLabel;
    @FXML private Label detailEventLabel;
    @FXML private Label detailUserLabel;
    @FXML private Label detailCreatedLabel;
    @FXML private HBox detailUsedBox;
    @FXML private Label detailUsedLabel;

    @FXML private Label totalTicketsLabel;
    @FXML private Label validatedTicketsLabel;
    @FXML private Label pendingTicketsLabel;

    private EventTicketService ticketService;
    private EventService eventService;

    @FXML
    public void initialize() {
        System.out.println("✅ TicketValidationController initialisé");

        ticketService = new EventTicketService();
        eventService = new EventService();

        updateDateTime();
        Timeline clock = new Timeline(
            new KeyFrame(Duration.ZERO, e -> updateDateTime()),
            new KeyFrame(Duration.seconds(1))
        );
        clock.setCycleCount(Timeline.INDEFINITE);
        clock.play();

        loadStatistics();

        // Focus automatique sur le champ
        ticketCodeField.requestFocus();

        // Validation avec Entrée
        ticketCodeField.setOnAction(e -> handleValidate());
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

    private void loadStatistics() {
        try {
            List<EventTicket> allTickets = ticketService.getAllTickets();

            int total = allTickets.size();
            int validated = (int) allTickets.stream().filter(EventTicket::isUsed).count();
            int pending = total - validated;

            totalTicketsLabel.setText(String.valueOf(total));
            validatedTicketsLabel.setText(String.valueOf(validated));
            pendingTicketsLabel.setText(String.valueOf(pending));

        } catch (Exception e) {
            System.err.println("❌ Erreur chargement statistiques: " + e.getMessage());
        }
    }

    @FXML
    private void handleValidate() {
        String code = ticketCodeField.getText().trim();

        if (code.isEmpty()) {
            showError("Champ vide", "Veuillez saisir un code de billet");
            return;
        }

        // Rechercher le ticket
        EventTicket ticket = ticketService.getTicketByCode(code);

        if (ticket == null) {
            showError("Billet invalide", "Ce code de billet n'existe pas dans notre système");
            return;
        }

        if (ticket.isUsed()) {
            showWarning(ticket);
            return;
        }

        // Marquer comme utilisé
        boolean success = ticketService.markTicketAsUsed(ticket.getId());

        if (success) {
            ticket.markAsUsed(); // Met à jour l'objet local
            showSuccess(ticket);
            loadStatistics(); // Recharger les stats
        } else {
            showError("Erreur système", "Impossible de valider le billet. Veuillez réessayer.");
        }
    }

    private void showSuccess(EventTicket ticket) {
        resultBox.setVisible(true);
        resultBox.setManaged(true);
        resultBox.setStyle("-fx-background-color: #d1fae5; -fx-padding: 40 30; -fx-background-radius: 10; -fx-border-radius: 10; -fx-border-color: #059669; -fx-border-width: 2;");

        resultIcon.setText("✅");
        resultIcon.setStyle("-fx-font-size: 60px;");

        resultTitle.setText("Entrée autorisée");
        resultTitle.setStyle("-fx-font-size: 24px; -fx-font-weight: 700; -fx-text-fill: #065f46; -fx-wrap-text: true;");

        resultMessage.setText("Le billet est valide. Accès accordé à l'événement.");
        resultMessage.setStyle("-fx-font-size: 14px; -fx-text-fill: #047857; -fx-wrap-text: true; -fx-text-alignment: center;");

        displayTicketDetails(ticket);

        resetBtn.setVisible(true);
        resetBtn.setManaged(true);
        validateBtn.setDisable(true);
        ticketCodeField.setDisable(true);
    }

    private void showWarning(EventTicket ticket) {
        resultBox.setVisible(true);
        resultBox.setManaged(true);
        resultBox.setStyle("-fx-background-color: #fef3c7; -fx-padding: 40 30; -fx-background-radius: 10; -fx-border-radius: 10; -fx-border-color: #f59e0b; -fx-border-width: 2;");

        resultIcon.setText("⚠️");
        resultIcon.setStyle("-fx-font-size: 60px;");

        resultTitle.setText("Billet déjà utilisé");
        resultTitle.setStyle("-fx-font-size: 24px; -fx-font-weight: 700; -fx-text-fill: #92400e; -fx-wrap-text: true;");

        String usedDate = ticket.getFormattedUsedAt();
        resultMessage.setText("Ce billet a déjà été scanné le " + usedDate + ". Accès refusé.");
        resultMessage.setStyle("-fx-font-size: 14px; -fx-text-fill: #b45309; -fx-wrap-text: true; -fx-text-alignment: center;");

        displayTicketDetails(ticket);

        resetBtn.setVisible(true);
        resetBtn.setManaged(true);
        validateBtn.setDisable(true);
        ticketCodeField.setDisable(true);
    }

    private void showError(String title, String message) {
        resultBox.setVisible(true);
        resultBox.setManaged(true);
        resultBox.setStyle("-fx-background-color: #fee2e2; -fx-padding: 40 30; -fx-background-radius: 10; -fx-border-radius: 10; -fx-border-color: #dc2626; -fx-border-width: 2;");

        resultIcon.setText("❌");
        resultIcon.setStyle("-fx-font-size: 60px;");

        resultTitle.setText(title);
        resultTitle.setStyle("-fx-font-size: 24px; -fx-font-weight: 700; -fx-text-fill: #991b1b; -fx-wrap-text: true;");

        resultMessage.setText(message);
        resultMessage.setStyle("-fx-font-size: 14px; -fx-text-fill: #dc2626; -fx-wrap-text: true; -fx-text-alignment: center;");

        ticketDetailsBox.setVisible(false);
        ticketDetailsBox.setManaged(false);

        resetBtn.setVisible(true);
        resetBtn.setManaged(true);
        validateBtn.setDisable(true);
        ticketCodeField.setDisable(true);
    }

    private void displayTicketDetails(EventTicket ticket) {
        ticketDetailsBox.setVisible(true);
        ticketDetailsBox.setManaged(true);

        detailCodeLabel.setText(ticket.getTicketCode());

        try {
            Event event = eventService.getEventById(ticket.getEventId());
            if (event != null) {
                detailEventLabel.setText(event.getTitle());
            } else {
                detailEventLabel.setText("Événement #" + ticket.getEventId());
            }
        } catch (Exception e) {
            detailEventLabel.setText("Événement #" + ticket.getEventId());
        }

        detailUserLabel.setText("Participant #" + ticket.getUserId());
        detailCreatedLabel.setText(ticket.getFormattedCreatedAt());

        if (ticket.isUsed()) {
            detailUsedBox.setVisible(true);
            detailUsedBox.setManaged(true);
            detailUsedLabel.setText(ticket.getFormattedUsedAt());
        } else {
            detailUsedBox.setVisible(false);
            detailUsedBox.setManaged(false);
        }
    }

    @FXML
    private void handleReset() {
        ticketCodeField.clear();
        ticketCodeField.setDisable(false);
        validateBtn.setDisable(false);

        resultBox.setVisible(false);
        resultBox.setManaged(false);

        resetBtn.setVisible(false);
        resetBtn.setManaged(false);

        ticketCodeField.requestFocus();

        loadStatistics();
    }
}

