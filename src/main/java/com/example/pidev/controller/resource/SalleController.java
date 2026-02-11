package com.example.pidev.controller.resource;

import com.example.pidev.model.resource.Salle;
import com.example.pidev.service.resource.SalleService;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.scene.layout.HBox;
import javafx.scene.web.WebView;
import javafx.stage.FileChooser;
import java.io.File;
import java.net.URL;
import java.util.Comparator;
import java.util.ResourceBundle;

public class SalleController implements Initializable {
    @FXML private TextField nameField, capacityField, buildingField, floorField;
    @FXML private ComboBox<String> statusCombo;
    @FXML private TableView<Salle> salleTable;
    @FXML private TableColumn<Salle, String> imageCol, nameCol, buildingCol, statusCol;
    @FXML private TableColumn<Salle, Integer> idCol, capacityCol, floorCol;
    @FXML private TableColumn<Salle, Void> actionCol;
    @FXML private ImageView previewImage;
    @FXML private Label totalSallesLabel, sallesOccupeesLabel;

    @FXML private WebView mapWebView;

    private final SalleService service = new SalleService();
    private ObservableList<Salle> masterData = FXCollections.observableArrayList();
    private String currentImagePath = "";
    private int selectedId = -1;
    private boolean triAscendant = true;

    // --- COORDONNÉES FIXES PAR BÂTIMENT ---
    private static final double LAT_BAT_A = 36.8985, LON_BAT_A = 10.1895;
    private static final double LAT_BAT_B = 36.8990, LON_BAT_B = 10.1900;
    private static final double LAT_BAT_C = 36.9000, LON_BAT_C = 10.1910;
    private static final double LAT_DEFAULT = 36.8065, LON_DEFAULT = 10.1815;

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        statusCombo.setItems(FXCollections.observableArrayList("DISPONIBLE", "OCCUPEE"));

        idCol.setCellValueFactory(new PropertyValueFactory<>("id"));
        nameCol.setCellValueFactory(new PropertyValueFactory<>("name"));
        capacityCol.setCellValueFactory(new PropertyValueFactory<>("capacity"));
        buildingCol.setCellValueFactory(new PropertyValueFactory<>("building"));
        floorCol.setCellValueFactory(new PropertyValueFactory<>("floor"));
        statusCol.setCellValueFactory(new PropertyValueFactory<>("status"));

        imageCol.setCellValueFactory(new PropertyValueFactory<>("imagePath"));
        imageCol.setCellFactory(param -> new TableCell<Salle, String>() {
            private final ImageView view = new ImageView();
            @Override protected void updateItem(String path, boolean empty) {
                super.updateItem(path, empty);
                if (empty || path == null || path.isEmpty()) {
                    setGraphic(null);
                } else {
                    try {
                        view.setImage(new Image(path, 40, 40, true, true));
                        setGraphic(view);
                    } catch (Exception e) {
                        setGraphic(null);
                    }
                }
            }
        });

        // MODIFICATION : Mise à jour de la carte BASÉE SUR LE BÂTIMENT lors de la sélection
        salleTable.getSelectionModel().selectedItemProperty().addListener((obs, oldSel, newSel) -> {
            if (newSel != null) {
                remplir(newSel);
                // On cherche les coordonnées du bâtiment de la salle sélectionnée
                double[] coords = getCoordsByBatiment(newSel.getBuilding());
                updateMap(coords[0], coords[1], "Bâtiment " + newSel.getBuilding() + " - " + newSel.getName());
            }
        });

        setupActionColumn();
        loadTable();
        updateMap(LAT_DEFAULT, LON_DEFAULT, "Sélectionnez une salle");
    }

    // MÉTHODE UTILITAIRE : Centralise la logique des coordonnées
    private double[] getCoordsByBatiment(String bat) {
        if (bat == null) return new double[]{LAT_DEFAULT, LON_DEFAULT};
        switch (bat.trim().toUpperCase()) {
            case "A": return new double[]{LAT_BAT_A, LON_BAT_A};
            case "B": return new double[]{LAT_BAT_B, LON_BAT_B};
            case "C": return new double[]{LAT_BAT_C, LON_BAT_C};
            default:  return new double[]{LAT_DEFAULT, LON_DEFAULT};
        }
    }
    //lien de lapi
    private void updateMap(double lat, double lon, String title) {
        String html = "<html><head>" +
                "<link rel='stylesheet' href='https://unpkg.com/leaflet@1.7.1/dist/leaflet.css'/>" +
                "<script src='https://unpkg.com/leaflet@1.7.1/dist/leaflet.js'></script>" +
                "<style>#map {height: 100%; width: 100%; margin:0; padding:0;}</style>" +
                "</head><body><div id='map'></div><script>" +
                "var map = L.map('map').setView([" + lat + ", " + lon + "], 18);" +
                "L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);" +
                "L.marker([" + lat + ", " + lon + "]).addTo(map).bindPopup('" + title + "').openPopup();" +
                "</script></body></html>";
        if (mapWebView != null) {
            mapWebView.getEngine().loadContent(html);
        }
    }

    @FXML
    void ajouterSalle() {
        try {
            if (nameField.getText().isEmpty() || buildingField.getText().isEmpty() || statusCombo.getValue() == null) {
                new Alert(Alert.AlertType.WARNING, "Veuillez remplir les champs obligatoires").show();
                return;
            }

            String batiment = buildingField.getText().trim().toUpperCase();
            double[] coords = getCoordsByBatiment(batiment);

            Salle s = new Salle(
                    selectedId == -1 ? 0 : selectedId,
                    nameField.getText(),
                    Integer.parseInt(capacityField.getText()),
                    batiment,
                    Integer.parseInt(floorField.getText()),
                    statusCombo.getValue(),
                    currentImagePath,
                    coords[0],
                    coords[1]
            );

            if (selectedId == -1) service.ajouter(s);
            else service.modifier(s);

            loadTable();
            updateMap(coords[0], coords[1], "Bâtiment " + batiment + " - " + s.getName());
            vider();
        } catch (NumberFormatException e) {
            new Alert(Alert.AlertType.ERROR, "La capacité et l'étage doivent être des nombres").show();
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    @FXML
    void choisirImage() {
        FileChooser fc = new FileChooser();
        fc.getExtensionFilters().add(new FileChooser.ExtensionFilter("Images", "*.png", "*.jpg", "*.jpeg"));
        File file = fc.showOpenDialog(null);
        if (file != null) {
            currentImagePath = file.toURI().toString();
            previewImage.setImage(new Image(currentImagePath));
        }
    }

    @FXML
    private void trierParEtage() {
        masterData.sort(triAscendant ?
                Comparator.comparingInt(Salle::getFloor) :
                Comparator.comparingInt(Salle::getFloor).reversed());
        triAscendant = !triAscendant;
    }

    private void loadTable() {
        masterData.setAll(service.afficher());
        salleTable.setItems(masterData);
        updateStats();
    }

    private void vider() {
        nameField.clear(); capacityField.clear(); buildingField.clear(); floorField.clear();
        statusCombo.setValue(null); previewImage.setImage(null);
        selectedId = -1; currentImagePath = "";
    }

    private void updateStats() {
        totalSallesLabel.setText(String.valueOf(masterData.size()));
        long occ = masterData.stream()
                .filter(s -> s.getStatus() != null && "OCCUPEE".equalsIgnoreCase(s.getStatus()))
                .count();
        sallesOccupeesLabel.setText(String.valueOf(occ));
    }

    private void setupActionColumn() {
        actionCol.setCellFactory(p -> new TableCell<Salle, Void>() {
            private final Button edit = new Button("✎"), del = new Button("🗑");
            private final HBox box = new HBox(5, edit, del);
            {
                del.setStyle("-fx-text-fill: red;");
                edit.setOnAction(e -> remplir(getTableView().getItems().get(getIndex())));
                del.setOnAction(e -> {
                    service.supprimer(getTableView().getItems().get(getIndex()).getId());
                    loadTable();
                });
            }
            @Override protected void updateItem(Void i, boolean e) {
                super.updateItem(i, e);
                setGraphic(e ? null : box);
            }
        });
    }

    private void remplir(Salle s) {
        selectedId = s.getId();
        nameField.setText(s.getName());
        capacityField.setText(String.valueOf(s.getCapacity()));
        buildingField.setText(s.getBuilding());
        floorField.setText(String.valueOf(s.getFloor()));
        statusCombo.setValue(s.getStatus());
        currentImagePath = s.getImagePath();

        if (currentImagePath != null && !currentImagePath.isEmpty()) {
            try { previewImage.setImage(new Image(currentImagePath)); } catch (Exception e) { previewImage.setImage(null); }
        }
    }
}