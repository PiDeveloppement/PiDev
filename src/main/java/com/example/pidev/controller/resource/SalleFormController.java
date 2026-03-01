package com.example.pidev.controller.resource;

import com.example.pidev.MainController;
import com.example.pidev.model.resource.Salle;
import com.example.pidev.service.resource.SalleService;
import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.stage.FileChooser;

import java.io.File;
import java.net.URL;
import java.util.ResourceBundle;

public class SalleFormController implements Initializable {

    @FXML private TextField nameField, capacityField, buildingField, floorField;
    @FXML private ComboBox<String> statusCombo;
    @FXML private Label titleLabel;
    @FXML private ImageView previewImage;

    private final SalleService service = new SalleService();
    private String currentImagePath = "";
    private int selectedId = -1;

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        statusCombo.setItems(FXCollections.observableArrayList("DISPONIBLE", "OCCUPEE"));
    }

    public void setSalleData(Salle s) {
        selectedId = s.getId();
        nameField.setText(s.getName());
        capacityField.setText(String.valueOf(s.getCapacity()));
        buildingField.setText(s.getBuilding());
        floorField.setText(String.valueOf(s.getFloor()));
        statusCombo.setValue(s.getStatus());
        currentImagePath = s.getImagePath();
        if (currentImagePath != null && !currentImagePath.isEmpty()) {
            try { previewImage.setImage(new Image(currentImagePath)); } catch (Exception e) {}
        }
        titleLabel.setText("Modifier la Salle");
    }

    // ================= CONTRÔLE DE SAISIE =================
    private boolean estValide() {
        StringBuilder error = new StringBuilder();

        if (nameField.getText().trim().isEmpty()) error.append("- Nom obligatoire\n");
        if (buildingField.getText().trim().isEmpty()) error.append("- Bâtiment obligatoire\n");
        if (statusCombo.getValue() == null) error.append("- Statut obligatoire\n");

        try {
            int cap = Integer.parseInt(capacityField.getText());
            if (cap <= 0) error.append("- Capacité doit être positive\n");
        } catch (NumberFormatException e) {
            error.append("- Capacité doit être un nombre\n");
        }

        try {
            Integer.parseInt(floorField.getText());
        } catch (NumberFormatException e) {
            error.append("- Étage doit être un nombre\n");
        }

        if (error.length() > 0) {
            Alert alert = new Alert(Alert.AlertType.WARNING);
            alert.setTitle("Validation");
            alert.setHeaderText("Champs invalides");
            alert.setContentText(error.toString());
            alert.showAndWait();
            return false;
        }
        return true;
    }

    @FXML
    private void choisirImage() {
        FileChooser fc = new FileChooser();
        fc.getExtensionFilters().add(new FileChooser.ExtensionFilter("Images", "*.png", "*.jpg", "*.jpeg"));
        File f = fc.showOpenDialog(null);
        if (f != null) {
            currentImagePath = f.toURI().toString();
            previewImage.setImage(new Image(currentImagePath));
        }
    }

    @FXML
    private void enregistrer() {
        if (estValide()) {
            try {
                Salle s = new Salle(
                        selectedId == -1 ? 0 : selectedId,
                        nameField.getText().trim(),
                        Integer.parseInt(capacityField.getText().trim()),
                        buildingField.getText().trim(),
                        Integer.parseInt(floorField.getText().trim()),
                        statusCombo.getValue(),
                        currentImagePath,
                        0.0, 0.0 // Lat/Long par défaut
                );

                if (selectedId == -1) service.ajouter(s);
                else service.modifier(s);

                annuler(); // Retour à la liste
            } catch (Exception e) {
                new Alert(Alert.AlertType.ERROR, "Erreur lors de la sauvegarde").show();
            }
        }
    }

    @FXML
    private void annuler() {
        MainController.getInstance().showSalles();
    }
}