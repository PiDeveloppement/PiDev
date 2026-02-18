package com.example.pidev.controller.resource;

import com.example.pidev.model.resource.*;
import com.example.pidev.service.resource.*;
import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.stage.Stage;
import java.io.IOException;
import java.net.URL;
import java.time.LocalDateTime;

public class ReservationFormController {

    @FXML private ComboBox<String> typeCombo;
    @FXML private ComboBox<Object> itemCombo;
    @FXML private DatePicker startDatePicker, endDatePicker;
    @FXML private TextField quantityField;
    @FXML private ImageView imagePreview;
    @FXML private Button btnValider;

    private final ReservationService resService = new ReservationService();
    private final SalleService salleService = new SalleService();
    private final EquipementService eqService = new EquipementService();
    private ReservationResource existingRes = null;

    @FXML public void initialize() {
        typeCombo.setItems(FXCollections.observableArrayList("SALLE", "EQUIPEMENT"));
        typeCombo.setOnAction(e -> chargerRessources());
        itemCombo.setOnAction(e -> mettreAJourApercu());
    }

    public void setReservationToEdit(ReservationResource res) {
        this.existingRes = res;
        if (res != null) {
            typeCombo.setValue(res.getResourceType());
            chargerRessources();
            startDatePicker.setValue(res.getStartTimedate().toLocalDate());
            endDatePicker.setValue(res.getEndTime().toLocalDate());
            quantityField.setText(String.valueOf(res.getQuantity()));
            if(btnValider != null) btnValider.setText("Mettre à jour");
        }
    }

    private void chargerRessources() {
        itemCombo.getItems().clear();
        if ("SALLE".equals(typeCombo.getValue())) itemCombo.setItems(FXCollections.observableArrayList(salleService.afficher()));
        else itemCombo.setItems(FXCollections.observableArrayList(eqService.afficher()));
    }

    private void mettreAJourApercu() {
        Object sel = itemCombo.getValue();
        if (sel != null) {
            String p = (sel instanceof Salle s) ? s.getImagePath() : ((Equipement)sel).getImagePath();
            if (p != null) try { imagePreview.setImage(new Image(p)); } catch(Exception ex){ imagePreview.setImage(null); }
        }
    }

    @FXML void handleSave() {
        try {
            if (itemCombo.getValue() == null || startDatePicker.getValue() == null) {
                new Alert(Alert.AlertType.WARNING, "Veuillez remplir les champs").show();
                return;
            }
            LocalDateTime start = startDatePicker.getValue().atTime(8, 0);
            LocalDateTime end = (endDatePicker.getValue() == null) ? start.plusHours(2) : endDatePicker.getValue().atTime(18, 0);
            Object selected = itemCombo.getValue();
            int qty = Integer.parseInt(quantityField.getText());
            int id = (existingRes == null) ? 0 : existingRes.getId();

            // Logique de validation simplifiée
            ReservationResource res = new ReservationResource(id, typeCombo.getValue(),
                    (selected instanceof Salle s) ? s.getId() : null, (selected instanceof Equipement eq) ? eq.getId() : null,
                    start, end, qty);

            if (existingRes == null) resService.ajouter(res);
            else resService.modifier(res);

            goToList();
        } catch (Exception ex) {
            new Alert(Alert.AlertType.ERROR, "Erreur : " + ex.getMessage()).show();
        }
    }

    @FXML void goToList() throws IOException {
        URL url = getClass().getResource("/com/example/pidev/fxml/resource/reservation.fxml");
        if (url == null) {
            System.err.println("Fichier reservation.fxml introuvable");
            return;
        }
        Parent root = FXMLLoader.load(url);
        // Sécurité : Utiliser typeCombo car btnValider peut être null si mal lié dans le FXML
        Stage stage = (Stage) typeCombo.getScene().getWindow();
        stage.getScene().setRoot(root);
    }
}