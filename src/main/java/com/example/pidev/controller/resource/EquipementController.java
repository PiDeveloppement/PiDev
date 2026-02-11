package com.example.pidev.controller.resource;

// CORRECTION DES IMPORTS : Pointent vers le sous-package .resource
import com.example.pidev.model.resource.Equipement;
import com.example.pidev.service.resource.EquipementService;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.collections.transformation.FilteredList;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.scene.layout.HBox;
import javafx.stage.FileChooser;
import java.io.File;
import java.net.URL;
import java.util.ResourceBundle;

public class EquipementController implements Initializable {
    @FXML private TextField nameField, typeField, quantityField, searchField;
    @FXML private ComboBox<String> statusCombo;
    @FXML private Button btnValider;
    @FXML private Label totalArticlesLabel, aReparerLabel;
    @FXML private ImageView previewImage;

    @FXML private TableView<Equipement> equipementTable;
    @FXML private TableColumn<Equipement, String> imageCol, nameCol, typeCol, statusCol;
    @FXML private TableColumn<Equipement, Integer> idCol, quantityCol;
    @FXML private TableColumn<Equipement, Void> actionCol;

    private final EquipementService service = new EquipementService();
    private ObservableList<Equipement> masterData = FXCollections.observableArrayList();
    private String currentImagePath = "";
    private int selectedId = -1;

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        statusCombo.setItems(FXCollections.observableArrayList("DISPONIBLE", "EN_PANNE"));

        idCol.setCellValueFactory(new PropertyValueFactory<>("id"));
        nameCol.setCellValueFactory(new PropertyValueFactory<>("name"));
        typeCol.setCellValueFactory(new PropertyValueFactory<>("type"));
        statusCol.setCellValueFactory(new PropertyValueFactory<>("status"));
        quantityCol.setCellValueFactory(new PropertyValueFactory<>("quantity"));

        // Affichage de l'image dans le tableau
        imageCol.setCellValueFactory(new PropertyValueFactory<>("imagePath"));
        imageCol.setCellFactory(param -> new TableCell<Equipement, String>() {
            private final ImageView view = new ImageView();
            @Override protected void updateItem(String path, boolean empty) {
                super.updateItem(path, empty);
                if (empty || path == null || path.isEmpty()) {
                    setGraphic(null);
                } else {
                    try {
                        // FitHeight/Width pour éviter que l'image ne dépasse de la ligne
                        view.setFitHeight(40);
                        view.setFitWidth(40);
                        view.setPreserveRatio(true);
                        view.setImage(new Image(path, true)); // true pour chargement en arrière-plan
                        setGraphic(view);
                    } catch (Exception e) {
                        setGraphic(null);
                    }
                }
            }
        });

        setupActionColumn();
        loadTable();
        setupSearch();
    }

    private void setupSearch() {
        FilteredList<Equipement> filteredData = new FilteredList<>(masterData, p -> true);
        if (searchField != null) {
            searchField.textProperty().addListener((obs, old, newValue) -> {
                filteredData.setPredicate(eq -> {
                    if (newValue == null || newValue.isEmpty()) return true;
                    String lower = newValue.toLowerCase();
                    return eq.getName().toLowerCase().contains(lower) ||
                            eq.getType().toLowerCase().contains(lower);
                });
            });
        }
        equipementTable.setItems(filteredData);
    }

    @FXML
    void choisirImage() {
        FileChooser fc = new FileChooser();
        // Filtre pour ne montrer que les images
        fc.getExtensionFilters().add(new FileChooser.ExtensionFilter("Images", "*.png", "*.jpg", "*.jpeg"));
        File selectedFile = fc.showOpenDialog(null);
        if (selectedFile != null) {
            currentImagePath = selectedFile.toURI().toString();
            previewImage.setImage(new Image(currentImagePath));
        }
    }

    @FXML
    void enregistrer() {
        try {
            if (nameField.getText().isEmpty() || statusCombo.getValue() == null) {
                new Alert(Alert.AlertType.WARNING, "Veuillez remplir les champs obligatoires").show();
                return;
            }
            int qty = Integer.parseInt(quantityField.getText());

            Equipement e = new Equipement(selectedId == -1 ? 0 : selectedId,
                    nameField.getText(), typeField.getText(),
                    statusCombo.getValue(), qty, currentImagePath);

            if (selectedId == -1) service.ajouter(e);
            else service.modifier(e);

            loadTable();
            viderChamps();
        } catch (NumberFormatException ex) {
            new Alert(Alert.AlertType.ERROR, "La quantité doit être un nombre").show();
        } catch (Exception ex) {
            ex.printStackTrace();
        }
    }

    private void loadTable() {
        masterData.setAll(service.afficher());
        updateStatistics();
    }

    private void setupActionColumn() {
        actionCol.setCellFactory(param -> new TableCell<Equipement, Void>() {
            private final Button editBtn = new Button("✎");
            private final Button deleteBtn = new Button("🗑");
            private final HBox pane = new HBox(10, editBtn, deleteBtn);
            {
                editBtn.setStyle("-fx-cursor: hand;");
                deleteBtn.setStyle("-fx-cursor: hand; -fx-text-fill: red;");

                editBtn.setOnAction(e -> remplirFormulaire(getTableView().getItems().get(getIndex())));
                deleteBtn.setOnAction(e -> {
                    Equipement selected = getTableView().getItems().get(getIndex());
                    service.supprimer(selected.getId());
                    loadTable();
                });
            }
            @Override protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                setGraphic(empty ? null : pane);
            }
        });
    }

    private void remplirFormulaire(Equipement e) {
        selectedId = e.getId();
        nameField.setText(e.getName());
        typeField.setText(e.getType());
        quantityField.setText(String.valueOf(e.getQuantity()));
        statusCombo.setValue(e.getStatus());
        currentImagePath = e.getImagePath();
        if (currentImagePath != null && !currentImagePath.isEmpty()) {
            try {
                previewImage.setImage(new Image(currentImagePath));
            } catch (Exception ex) {
                previewImage.setImage(null);
            }
        }
        btnValider.setText("Mettre à jour");
    }

    @FXML
    void viderChamps() {
        nameField.clear(); typeField.clear(); quantityField.clear();
        statusCombo.setValue(null); previewImage.setImage(null);
        selectedId = -1; currentImagePath = "";
        btnValider.setText("Enregistrer");
    }

    private void updateStatistics() {
        totalArticlesLabel.setText(String.valueOf(masterData.size()));
        long count = masterData.stream()
                .filter(e -> e.getStatus() != null && "EN_PANNE".equalsIgnoreCase(e.getStatus()))
                .count();
        aReparerLabel.setText(String.valueOf(count));
    }
}