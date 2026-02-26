package com.example.pidev.controller.front;

import com.example.pidev.HelloApplication;
import com.example.pidev.model.event.EventTicket;
import com.example.pidev.service.event.EventTicketService;
import com.example.pidev.utils.UserSession;
import javafx.fxml.FXML;
import javafx.geometry.Insets;
import javafx.scene.control.*;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;

import java.util.List;

/**
 * Contrôleur pour afficher la liste des billets du participant
 */
public class MyTicketsListController {

    @FXML private Button backBtn;
    @FXML private Label userNameLabel;
    @FXML private Label ticketCountLabel;
    @FXML private VBox ticketsContainer;
    @FXML private VBox emptyStateBox;

    private EventTicketService ticketService;

    @FXML
    public void initialize() {
        System.out.println("✅ MyTicketsListController initialisé");
        ticketService = new EventTicketService();

        loadUserInfo();
        loadMyTickets();

        backBtn.setOnAction(e -> HelloApplication.loadPublicEventsPage());
    }

    private void loadUserInfo() {
        String userName = UserSession.getInstance().getCurrentUser().getFirst_Name() + " " +
                UserSession.getInstance().getCurrentUser().getLast_Name();
        userNameLabel.setText(userName);
    }

    private void loadMyTickets() {
        int userId = UserSession.getInstance().getCurrentUser().getId_User();
        List<EventTicket> tickets = ticketService.getTicketsByUser(userId);

        ticketsContainer.getChildren().clear();

        if (tickets.isEmpty()) {
            emptyStateBox.setVisible(true);
            ticketCountLabel.setText("0 billet(s) trouvé(s)");
        } else {
            emptyStateBox.setVisible(false);
            ticketCountLabel.setText(tickets.size() + " billet(s) trouvé(s)");

            for (EventTicket ticket : tickets) {
                ticketsContainer.getChildren().add(createTicketCard(ticket));
            }
        }
    }

    private HBox createTicketCard(EventTicket ticket) {
        HBox card = new HBox(20);
        card.setStyle("-fx-background-color: white; -fx-border-radius: 8; -fx-background-radius: 8; " +
                "-fx-padding: 20; -fx-border-color: #e5e7eb; -fx-border-width: 1;");
        card.setPrefHeight(200);

        // QR Code
        VBox qrContainer = new VBox();
        qrContainer.setPrefWidth(180);
        qrContainer.setStyle("-fx-alignment: center; -fx-background-color: #f9fafb; -fx-border-radius: 6; -fx-padding: 10;");

        String qrUrl = ticket.getQrCode();
        if (qrUrl != null && !qrUrl.isBlank()) {
            ImageView qrImage = new ImageView();
            qrImage.setFitWidth(160.0);
            qrImage.setFitHeight(160.0);
            qrImage.setImage(new Image(qrUrl, true));
            qrImage.setPreserveRatio(true);
            qrContainer.getChildren().add(qrImage);
        } else {
            Label noQrLabel = new Label("Pas de QR");
            noQrLabel.setStyle("-fx-text-fill: #9ca3af;");
            qrContainer.getChildren().add(noQrLabel);
        }

        // Infos du billet
        VBox infoContainer = new VBox(12);
        infoContainer.setStyle("-fx-spacing: 12;");

        Label codeLabel = new Label("Code : " + ticket.getTicketCode());
        codeLabel.setStyle("-fx-font-size: 14; -fx-font-weight: bold; -fx-text-fill: #1f2937;");

        Label eventLabel = new Label("Événement #" + ticket.getEventId());
        eventLabel.setStyle("-fx-font-size: 12; -fx-text-fill: #6b7280;");

        Label createdLabel = new Label("Créé : " + ticket.getFormattedCreatedAt());
        createdLabel.setStyle("-fx-font-size: 11; -fx-text-fill: #9ca3af;");

        Label statusLabel = new Label(ticket.isUsed() ? "✓ Utilisé" : "Valide");
        statusLabel.setStyle("-fx-font-size: 12; -fx-font-weight: bold; " +
                (ticket.isUsed() ? "-fx-text-fill: #dc2626;" : "-fx-text-fill: #059669;"));

        Button viewBtn = new Button("Voir détail");
        viewBtn.setStyle("-fx-padding: 8 16; -fx-font-size: 12; -fx-background-radius: 6;");
        viewBtn.setOnAction(e -> showTicketDetail(ticket));

        infoContainer.getChildren().addAll(codeLabel, eventLabel, createdLabel, statusLabel, viewBtn);

        card.getChildren().addAll(qrContainer, infoContainer);
        return card;
    }

    private void showTicketDetail(EventTicket ticket) {
        System.out.println("📋 Affichage du détail du billet : " + ticket.getTicketCode());
        // TODO: Ouvrir la page détail du billet en front office
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Détail du billet");
        alert.setHeaderText("Code : " + ticket.getTicketCode());
        alert.setContentText("Billet créé le : " + ticket.getFormattedCreatedAt());
        alert.showAndWait();
    }
}



