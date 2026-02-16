package com.example.pidev.controller.sponsor;

import com.example.pidev.model.sponsor.Sponsor;
import com.example.pidev.service.sponsor.SponsorService;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.scene.layout.HBox;
import javafx.scene.paint.Color;
import javafx.util.Callback;

import java.net.URL;
import java.util.ResourceBundle;

public class SponsorListController implements Initializable {

    @FXML private TableView<Sponsor> sponsorsTable;
    @FXML private TableColumn<Sponsor, Integer> idCol;
    @FXML private TableColumn<Sponsor, Integer> eventIdCol;
    @FXML private TableColumn<Sponsor, String> companyNameCol;
    @FXML private TableColumn<Sponsor, String> logoCol;
    @FXML private TableColumn<Sponsor, Double> contributionCol;
    @FXML private TableColumn<Sponsor, String> emailCol;
    @FXML private TableColumn<Sponsor, Void> actionsCol;

    @FXML private Button addSponsorBtn;
    @FXML private Label statsLabel;

    @FXML private Label totalSponsorsLabel;
    @FXML private Label totalContributionLabel;
    @FXML private Label avgContributionLabel;

    private ObservableList<Sponsor> sponsorsList = FXCollections.observableArrayList();
    private SponsorService sponsorService = new SponsorService();

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        setupTableColumns();
        loadSponsors();
        setupActionsColumn();
        updateStats();

        addSponsorBtn.setOnAction(e -> handleAddSponsor());
    }

    private void setupTableColumns() {
        idCol.setCellValueFactory(new PropertyValueFactory<>("id"));
        eventIdCol.setCellValueFactory(new PropertyValueFactory<>("event_id"));
        companyNameCol.setCellValueFactory(new PropertyValueFactory<>("company_name"));
        contributionCol.setCellValueFactory(new PropertyValueFactory<>("contribution_amt"));
        emailCol.setCellValueFactory(new PropertyValueFactory<>("contact_email"));

        // Colonne Logo avec image
        logoCol.setCellFactory(param -> new TableCell<Sponsor, String>() {
            private final ImageView imageView = new ImageView();

            {
                imageView.setFitHeight(40);
                imageView.setFitWidth(40);
                imageView.setPreserveRatio(true);
                setGraphic(imageView);
            }

            @Override
            protected void updateItem(String logoUrl, boolean empty) {
                super.updateItem(logoUrl, empty);

                if (empty || logoUrl == null || logoUrl.isEmpty()) {
                    setGraphic(null);
                } else {
                    try {
                        imageView.setImage(new Image(logoUrl, true));
                        imageView.setStyle("-fx-background-radius: 20; -fx-border-radius: 20;");
                    } catch (Exception e) {
                        // Image par défaut si l'URL échoue
                        ImageView defaultIcon = new ImageView();
                        defaultIcon.setFitHeight(40);
                        defaultIcon.setFitWidth(40);
                        defaultIcon.setStyle("-fx-background-color: #e2e8f0; -fx-background-radius: 20; -fx-padding: 10;");
                        setGraphic(defaultIcon);
                    }
                }
            }
        });

        // Formatage de la colonne contribution
        contributionCol.setCellFactory(column -> new TableCell<Sponsor, Double>() {
            @Override
            protected void updateItem(Double amount, boolean empty) {
                super.updateItem(amount, empty);

                if (empty || amount == null) {
                    setText(null);
                    setStyle("");
                } else {
                    setText(String.format("%,.2f DT", amount));
                    setStyle("-fx-alignment: CENTER_RIGHT; -fx-font-weight: bold; -fx-text-fill: #059669;");
                }
            }
        });
    }

    private void setupActionsColumn() {
        actionsCol.setCellFactory(param -> new TableCell<Sponsor, Void>() {
            private final HBox container = new HBox(8);
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

                updateBtn.setOnAction(e -> {
                    Sponsor sponsor = getTableView().getItems().get(getIndex());
                    handleUpdateSponsor(sponsor);
                });

                deleteBtn.setOnAction(e -> {
                    Sponsor sponsor = getTableView().getItems().get(getIndex());
                    handleDeleteSponsor(sponsor);
                });

                container.getChildren().addAll(updateBtn, deleteBtn);
                container.setAlignment(javafx.geometry.Pos.CENTER);
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                setGraphic(empty ? null : container);
            }
        });
    }

    private void loadSponsors() {
        sponsorsList.setAll(sponsorService.getAllSponsors());
        sponsorsTable.setItems(sponsorsList);
        statsLabel.setText("📊 " + sponsorsList.size() + " sponsors trouvés • 🔄 Dernière mise à jour: Maintenant");
    }

    private void updateStats() {
        totalSponsorsLabel.setText(String.valueOf(sponsorService.getTotalSponsors()));
        totalContributionLabel.setText(String.format("%,.2f DT", sponsorService.getTotalContribution()));
        avgContributionLabel.setText(String.format("%,.2f DT", sponsorService.getAverageContribution()));
    }

    private void handleAddSponsor() {
        System.out.println("Ajouter un nouveau sponsor");
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Ajouter un sponsor");
        alert.setHeaderText("Fonctionnalité d'ajout");
        alert.setContentText("Bouton 'Nouveau Sponsor' cliqué");
        alert.showAndWait();
    }

    private void handleUpdateSponsor(Sponsor sponsor) {
        System.out.println("Mettre à jour le sponsor: " + sponsor.getCompany_name());

        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle("Mise à jour");
        alert.setHeaderText("Mettre à jour le sponsor");
        alert.setContentText("✏️ Vous avez cliqué sur Modifier pour: " +
                sponsor.getCompany_name() + "\nID: " + sponsor.getId() +
                "\nContribution: " + sponsor.getContribution_amt() + " DT");
        alert.showAndWait();
    }

    private void handleDeleteSponsor(Sponsor sponsor) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Confirmation de suppression");
        confirm.setHeaderText("🗑️ Supprimer le sponsor");
        confirm.setContentText("Êtes-vous sûr de vouloir supprimer le sponsor: \n\n" +
                "🏢 " + sponsor.getCompany_name() + "\n" +
                "🔢 ID: " + sponsor.getId() + "\n" +
                "💰 Contribution: " + sponsor.getContribution_amt() + " DT\n" +
                "📧 Email: " + sponsor.getContact_email() + "\n\n" +
                "Cette action est irréversible !");

        confirm.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                sponsorsList.remove(sponsor);
                updateStats();
                statsLabel.setText("✅ Sponsor supprimé avec succès • 📊 " +
                        sponsorsList.size() + " sponsors restants");

                Alert success = new Alert(Alert.AlertType.INFORMATION);
                success.setTitle("Suppression réussie");
                success.setHeaderText("✅ Sponsor supprimé");
                success.setContentText("Le sponsor '" + sponsor.getCompany_name() + "' a été supprimé avec succès.");
                success.showAndWait();
            }
        });
    }
}
