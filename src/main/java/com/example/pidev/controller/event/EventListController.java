package com.example.pidev.controller.event;

import com.example.pidev.model.event.Event;
import javafx.beans.property.SimpleObjectProperty;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import javafx.scene.paint.Color;
import javafx.util.Callback;
import javafx.scene.control.TableCell;
import javafx.scene.control.TableColumn;

import java.net.URL;
import java.util.ResourceBundle;

public class EventListController implements Initializable {

    @FXML
    private TableView<Event> eventsTable;

    @FXML
    private TableColumn<Event, Integer> idCol;

    @FXML
    private TableColumn<Event, String> titleCol;

    @FXML
    private TableColumn<Event, String> dateCol;

    @FXML
    private TableColumn<Event, String> categoryCol;

    @FXML
    private TableColumn<Event, String> statusCol;

    @FXML
    private TableColumn<Event, Integer> participantsCol;

    @FXML
    private TableColumn<Event, Double> budgetCol;

    @FXML
    private TableColumn<Event, Void> actionsCol; // IMPORTANT: Type Void pour les actions

    @FXML
    private Button addEventBtn;

    @FXML
    private Label statusLabel;

    private ObservableList<Event> eventsList = FXCollections.observableArrayList();

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        setupTableColumns();
        loadEvents();
        setupActionsColumn(); // Cette méthode crée les boutons

        addEventBtn.setOnAction(e -> handleAddEvent());
    }

    private void setupTableColumns() {
        // Configurez les colonnes normales
        idCol.setCellValueFactory(new PropertyValueFactory<>("id"));
        titleCol.setCellValueFactory(new PropertyValueFactory<>("title"));
        dateCol.setCellValueFactory(new PropertyValueFactory<>("date"));
        categoryCol.setCellValueFactory(new PropertyValueFactory<>("category"));
        statusCol.setCellValueFactory(new PropertyValueFactory<>("status"));
        participantsCol.setCellValueFactory(new PropertyValueFactory<>("participants"));
        budgetCol.setCellValueFactory(new PropertyValueFactory<>("budget"));

        // Option: Utilisez lambda pour éviter les problèmes de module
        /*
        idCol.setCellValueFactory(cellData ->
            new SimpleObjectProperty<>(cellData.getValue().getId()));
        titleCol.setCellValueFactory(cellData ->
            new SimpleStringProperty(cellData.getValue().getTitle()));
        */
    }

    private void setupActionsColumn() {
        actionsCol.setCellFactory(param -> {
            return new TableCell<Event, Void>() {
                private final HBox container = new HBox(8);

                // Utiliser les codes Unicode directement
                private final Button updateBtn = new Button("\u270F"); // ✏
                private final Button deleteBtn = new Button("\uD83D\uDDD1"); // 🗑

                {
                    // Style des boutons
                    updateBtn.setStyle("-fx-background-color: #3b82f6; " +
                            "-fx-text-fill: white; " +
                            "-fx-font-size: 14px; " +
                            "-fx-font-family: 'Segoe UI Emoji', 'Apple Color Emoji', sans-serif; " +
                            "-fx-padding: 6 12; " +
                            "-fx-background-radius: 6; " +
                            "-fx-cursor: hand; " +
                            "-fx-min-width: 40px; " +
                            "-fx-min-height: 30px;");

                    deleteBtn.setStyle("-fx-background-color: #ef4444; " +
                            "-fx-text-fill: white; " +
                            "-fx-font-size: 14px; " +
                            "-fx-font-family: 'Segoe UI Emoji', 'Apple Color Emoji', sans-serif; " +
                            "-fx-padding: 6 12; " +
                            "-fx-background-radius: 6; " +
                            "-fx-cursor: hand; " +
                            "-fx-min-width: 40px; " +
                            "-fx-min-height: 30px;");

                    // Actions
                    updateBtn.setOnAction(e -> {
                        Event event = getTableView().getItems().get(getIndex());
                        handleUpdateEvent(event);
                    });

                    deleteBtn.setOnAction(e -> {
                        Event event = getTableView().getItems().get(getIndex());
                        handleDeleteEvent(event);
                    });

                    container.getChildren().addAll(updateBtn, deleteBtn);
                    container.setAlignment(javafx.geometry.Pos.CENTER);
                }

                @Override
                protected void updateItem(Void item, boolean empty) {
                    super.updateItem(item, empty);
                    setGraphic(empty ? null : container);
                }
            };
        });
    }

    private void loadEvents() {
        // Exemple de données de test avec plus d'événements
        eventsList.add(new Event(1, "Conférence IA", "2024-12-15", "Technologie",
                "Planifié", 150, 5000.0));
        eventsList.add(new Event(2, "Tournoi Sportif", "2024-11-20", "Sport",
                "En cours", 200, 3000.0));
        eventsList.add(new Event(3, "Atelier Développement", "2024-10-05", "Éducation",
                "Terminé", 80, 1500.0));
        eventsList.add(new Event(4, "Journée Portes Ouvertes", "2024-09-28", "Événement",
                "Planifié", 300, 8000.0));
        eventsList.add(new Event(5, "Séminaire Recherche", "2024-08-15", "Académique",
                "Terminé", 120, 4000.0));
        eventsList.add(new Event(6, "Festival Culturel", "2024-07-10", "Culture",
                "En cours", 500, 12000.0));

        eventsTable.setItems(eventsList);
        statusLabel.setText("📊 " + eventsList.size() + " événements trouvés • 🔄 Dernière mise à jour: Maintenant");
    }

    private void handleAddEvent() {
        System.out.println("Ajouter un nouvel événement");
        // Implémentez la logique d'ajout
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Ajouter un événement");
        alert.setHeaderText("Fonctionnalité d'ajout");
        alert.setContentText("Bouton 'Nouvel Événement' cliqué");
        alert.showAndWait();
    }

    private void handleUpdateEvent(Event event) {
        System.out.println("Mettre à jour l'événement: " + event.getTitle());

        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Mise à jour");
        alert.setHeaderText("Mettre à jour l'événement");
        alert.setContentText("✏️ Vous avez cliqué sur Modifier pour: " +
                event.getTitle() + "\nID: " + event.getId());
        alert.showAndWait();
    }

    private void handleDeleteEvent(Event event) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation de suppression");
        confirm.setHeaderText("🗑️ Supprimer l'événement");
        confirm.setContentText("Êtes-vous sûr de vouloir supprimer l'événement: \n\n" +
                "📝 " + event.getTitle() + "\n" +
                "🔢 ID: " + event.getId() + "\n" +
                "📅 Date: " + event.getDate() + "\n\n" +
                "Cette action est irréversible !");

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                eventsList.remove(event);
                statusLabel.setText("✅ Événement supprimé avec succès • 📊 " +
                        eventsList.size() + " événements restants");

                // Notification de succès
                Alert success = new Alert(Alert.AlertType.INFORMATION);
                success.setTitle("Suppression réussie");
                success.setHeaderText("✅ Événement supprimé");
                success.setContentText("L'événement '" + event.getTitle() + "' a été supprimé avec succès.");
                success.showAndWait();
            }
        });
    }
}

