package com.example.pidev.controller.event;

import com.example.pidev.HelloController;
import com.example.pidev.model.event.EventTicket;
import com.example.pidev.service.event.EventTicketService;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.layout.HBox;
import javafx.scene.paint.Color;
import javafx.scene.shape.SVGPath;
import javafx.util.Duration;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.List;
import java.util.Locale;

/**
 * Controller pour la liste des tickets
 * @author Ons Abdesslem
 */
public class EventTicketListController {

    // ========== TABLEAU ==========
    @FXML private TableView<EventTicket> ticketTable;
    @FXML private TableColumn<EventTicket, String> ticketCodeCol;
    @FXML private TableColumn<EventTicket, String> eventCol;
    @FXML private TableColumn<EventTicket, String> userCol;
    @FXML private TableColumn<EventTicket, String> statusCol;
    @FXML private TableColumn<EventTicket, String> createdAtCol;
    @FXML private TableColumn<EventTicket, Void> actionsCol;

    // ========== FILTRES ==========
    @FXML private TextField searchField;
    @FXML private ComboBox<String> eventFilter;
    @FXML private ComboBox<String> statusFilter;

    // ========== KPI ==========
    @FXML private Label totalLabel;
    @FXML private Label usedLabel;
    @FXML private Label resultLabel;

    // ========== NAVBAR ==========
    @FXML private Label dateLabel;
    @FXML private Label timeLabel;

    // ========== BOUTONS ==========
    @FXML private Button addBtn;
    @FXML private Button scanBtn;

    private EventTicketService ticketService;
    private HelloController helloController;
    private List<EventTicket> allTickets;
    private List<EventTicket> filteredTickets;


    @FXML
    public void initialize() {
        System.out.println("✅ EventTicketListController initialisé");

        ticketService = new EventTicketService();

        setupFilters();
        setupTableColumns();
        loadTickets();

        // ========== DATE/HEURE TEMPS RÉEL ==========
        updateDateTime();
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

    private void setupFilters() {
        searchField.setPromptText("🔍 Rechercher un ticket par code...");
        searchField.textProperty().addListener((obs, old, newVal) -> applyFilters());

        eventFilter.getItems().add("Tous les événements");
        eventFilter.setValue("Tous les événements");
        eventFilter.valueProperty().addListener((obs, old, newVal) -> applyFilters());

        statusFilter.getItems().addAll("Tous", "Utilisé", "Non utilisé");
        statusFilter.setValue("Tous");
        statusFilter.valueProperty().addListener((obs, old, newVal) -> applyFilters());
    }

    private void setupTableColumns() {
        // Code du ticket
        ticketCodeCol.setCellValueFactory(param ->
                new javafx.beans.property.SimpleStringProperty(param.getValue().getTicketCode())
        );

        // Événement
        eventCol.setCellValueFactory(param ->
                new javafx.beans.property.SimpleStringProperty("Event " + param.getValue().getEventId())
        );

        // Utilisateur
        userCol.setCellValueFactory(param ->
                new javafx.beans.property.SimpleStringProperty("User " + param.getValue().getUserId())
        );

        // Statut
        statusCol.setCellValueFactory(param ->
                new javafx.beans.property.SimpleStringProperty(
                        param.getValue().isUsed() ? "Utilisé" : "Non utilisé"
                )
        );
        statusCol.setCellFactory(col -> new TableCell<>() {
            @Override
            protected void updateItem(String status, boolean empty) {
                super.updateItem(status, empty);
                if (empty || status == null) {
                    setGraphic(null);
                } else {
                    Label badge = new Label(status);
                    badge.getStyleClass().add("status-badge");
                    if ("Utilisé".equals(status)) {
                        badge.getStyleClass().add("status-badge-inactive");
                    } else {
                        badge.getStyleClass().add("status-badge-active");
                    }
                    setGraphic(badge);
                }
            }
        });

        // Date création
        createdAtCol.setCellValueFactory(param ->
                new javafx.beans.property.SimpleStringProperty(param.getValue().getFormattedCreatedAt())
        );

        // Actions
        actionsCol.setCellFactory(col -> new TableCell<>() {
            private final Button viewBtn = createIconButton("eye", "#17a2b8");
            private final Button scanBtn = createIconButton("qrcode", "#0d47a1");
            private final Button deleteBtn = createIconButton("trash", "#dc3545");
            private final HBox container = new HBox(8, viewBtn, scanBtn, deleteBtn);
            {
                container.setAlignment(Pos.CENTER);
            }
            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                if (empty) {
                    setGraphic(null);
                } else {
                    EventTicket ticket = getTableView().getItems().get(getIndex());
                    viewBtn.setOnAction(e -> handleView(ticket));
                    scanBtn.setOnAction(e -> handleScan(ticket));
                    deleteBtn.setOnAction(e -> handleDelete(ticket));
                    setGraphic(container);
                }
            }
        });
    }

    private Button createIconButton(String iconType, String color) {
        Button btn = new Button();
        btn.setMinSize(38, 38);
        btn.setMaxSize(38, 38);
        btn.getStyleClass().add("action-btn-icon");
        SVGPath icon = new SVGPath();
        switch (iconType) {
            case "eye":
                icon.setContent("M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z");
                break;
            case "qrcode":
                icon.setContent("M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm14 0h-4v2h2v4h2v-4h2v-6h-2v4z");
                break;
            case "trash":
                icon.setContent("M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z");
                break;
        }
        icon.setFill(Color.WHITE);
        icon.setScaleX(0.7);
        icon.setScaleY(0.7);
        btn.setGraphic(icon);
        btn.setStyle("-fx-background-color: " + color + "; -fx-background-radius: 6;");
        return btn;
    }

    private void loadTickets() {
        try {
            allTickets = ticketService.getAllTickets();
            populateEventFilter();
            applyFilters();
            updateStatistics();
            System.out.println("✅ " + allTickets.size() + " tickets chargés");
        } catch (Exception e) {
            System.err.println("❌ Erreur: " + e.getMessage());
            showError("Erreur", "Impossible de charger les tickets");
        }
    }

    private void populateEventFilter() {
        // À implémenter avec les vrais événements
        eventFilter.getItems().clear();
        eventFilter.getItems().add("Tous les événements");
        eventFilter.getItems().add("Event 1");
        eventFilter.getItems().add("Event 2");
    }

    private void updateStatistics() {
        if (allTickets == null) return;
        int total = allTickets.size();
        long used = allTickets.stream().filter(EventTicket::isUsed).count();
        totalLabel.setText(String.valueOf(total));
        usedLabel.setText(String.valueOf(used));
    }

    private void applyFilters() {
        if (allTickets == null) return;

        String searchText = searchField.getText().toLowerCase().trim();
        String event = eventFilter.getValue();
        String status = statusFilter.getValue();

        List<EventTicket> filtered = allTickets.stream()
                .filter(ticket -> {
                    boolean matchSearch = searchText.isEmpty() ||
                            ticket.getTicketCode().toLowerCase().contains(searchText);

                    boolean matchEvent = event == null || "Tous les événements".equals(event) ||
                            ("Event " + ticket.getEventId()).equals(event);

                    boolean matchStatus = status == null || "Tous".equals(status) ||
                            (status.equals("Utilisé") && ticket.isUsed()) ||
                            (status.equals("Non utilisé") && !ticket.isUsed());

                    return matchSearch && matchEvent && matchStatus;
                })
                .toList();

        filteredTickets = filtered;
        ticketTable.getItems().clear();
        ticketTable.getItems().addAll(filtered);
        resultLabel.setText(filtered.size() + " résultat(s) trouvé(s)");
    }

    @FXML
    private void handleAdd() {
        // Pour l'instant, créer un ticket de test
        EventTicket ticket = ticketService.createTicket(1, 16); // Event 1, User Ons
        if (ticket != null) {
            loadTickets();
            showSuccess("Succès", "Ticket créé: " + ticket.getTicketCode());
        }
    }

    @FXML
    private void handleScan() {
        // Ouvrir la vue de scan QR code
        System.out.println("📱 Ouverture du scanner QR code");
        // TODO: Implémenter le scan
    }

    private void handleView(EventTicket ticket) {
        Alert details = new Alert(Alert.AlertType.INFORMATION);
        details.setTitle("Détails du ticket");
        details.setHeaderText("Ticket: " + ticket.getTicketCode());
        details.setContentText(String.format(
                "Événement ID: %d\nUtilisateur ID: %d\nStatut: %s\nCréé le: %s\nUtilisé le: %s",
                ticket.getEventId(),
                ticket.getUserId(),
                ticket.isUsed() ? "✅ Utilisé" : "❌ Non utilisé",
                ticket.getFormattedCreatedAt(),
                ticket.getFormattedUsedAt()
        ));
        details.showAndWait();
    }

    private void handleScan(EventTicket ticket) {
        // Marquer comme utilisé
        if (!ticket.isUsed()) {
            boolean success = ticketService.markTicketAsUsed(ticket.getId());
            if (success) {
                showSuccess("Succès", "Ticket scanné et marqué comme utilisé");
                loadTickets();
            } else {
                showError("Erreur", "Impossible de scanner le ticket");
            }
        } else {
            showError("Erreur", "Ce ticket a déjà été utilisé");
        }
    }

    private void handleDelete(EventTicket ticket) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation");
        confirm.setHeaderText("Supprimer le ticket " + ticket.getTicketCode() + " ?");
        confirm.setContentText("Cette action est irréversible.");

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                if (ticketService.deleteTicket(ticket.getId())) {
                    showSuccess("Succès", "Ticket supprimé");
                    loadTickets();
                } else {
                    showError("Erreur", "Impossible de supprimer");
                }
            }
        });
    }

    @FXML
    private void handleRefresh() {
        loadTickets();
    }

    private void showError(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.ERROR);
        alert.setTitle(title);
        alert.setContentText(message);
        alert.showAndWait();
    }

    private void showSuccess(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setContentText(message);
        alert.showAndWait();
    }
}