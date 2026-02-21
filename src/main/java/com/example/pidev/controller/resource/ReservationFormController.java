package com.example.pidev.controller.resource;

import com.example.pidev.MainController;
import com.example.pidev.model.resource.*;
import com.example.pidev.service.resource.*;
import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.scene.paint.Color;
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
    private ReservationResource selectedReservation = null;

    @FXML
    public void initialize() {
        typeCombo.setItems(FXCollections.observableArrayList("SALLE", "EQUIPEMENT"));
        typeCombo.setOnAction(e -> chargerRessources());
        itemCombo.setOnAction(e -> mettreAJourApercu());
        startDatePicker.valueProperty().addListener((obs, oldV, newV) -> refreshItemCombo());
        endDatePicker.valueProperty().addListener((obs, oldV, newV) -> refreshItemCombo());
        setupItemComboDesign();
    }

    public void setReservationToEdit(ReservationResource res) {
        this.selectedReservation = res;
        if (res != null) {
            typeCombo.setValue(res.getResourceType());
            chargerRessources();
            startDatePicker.valueProperty().addListener((obs, oldVal, newVal) -> {
                itemCombo.setCellFactory(null); // Force le rafraîchissement
                setupItemComboDesign();
            });
            endDatePicker.valueProperty().addListener((obs, oldVal, newVal) -> {
                itemCombo.setCellFactory(null);
                setupItemComboDesign();
            });
            quantityField.setText(String.valueOf(res.getQuantity()));
            btnValider.setText("Mettre à jour");
            for (Object obj : itemCombo.getItems()) {
                if (obj instanceof Salle s && s.getId() == res.getSalleId()) { itemCombo.setValue(obj); break; }
                if (obj instanceof Equipement eq && eq.getId() == res.getEquipementId()) { itemCombo.setValue(obj); break; }
            }
        }
    }

    @FXML
    void validerAction() {
        try {
            if (itemCombo.getValue() == null || startDatePicker.getValue() == null) return;
            LocalDateTime s = startDatePicker.getValue().atTime(8, 0);
            LocalDateTime e = endDatePicker.getValue() == null ? s.plusHours(2) : endDatePicker.getValue().atTime(18, 0);
            Object sel = itemCombo.getValue();
            int qtySaisie = Integer.parseInt(quantityField.getText());
            int currentId = (selectedReservation == null) ? -1 : selectedReservation.getId();

            int dispo = 0;
            if (sel instanceof Salle sa) dispo = resService.isSalleOccupee(sa.getId(), s, e, currentId) ? 0 : 1;
            else if (sel instanceof Equipement eq) dispo = resService.getStockTotalEquipement(eq.getId()) - resService.getStockOccupe(eq.getId(), s, e, currentId);

            if (qtySaisie > dispo) {
                new Alert(Alert.AlertType.ERROR, "Stock insuffisant : " + dispo).show();
                return;
            }

            ReservationResource res = new ReservationResource(
                    currentId == -1 ? 0 : currentId, typeCombo.getValue(),
                    (sel instanceof Salle sa) ? sa.getId() : null,
                    (sel instanceof Equipement eq) ? eq.getId() : null, s, e, qtySaisie
            );

            if (selectedReservation == null) resService.ajouter(res);
            else resService.modifier(res);

            goBack();
        } catch (Exception ex) { ex.printStackTrace(); }
    }

    private void chargerRessources() {
        itemCombo.getItems().clear();
        if ("SALLE".equals(typeCombo.getValue())) itemCombo.setItems(FXCollections.observableArrayList(salleService.afficher()));
        else if ("EQUIPEMENT".equals(typeCombo.getValue())) itemCombo.setItems(FXCollections.observableArrayList(eqService.afficher()));
    }

    private void mettreAJourApercu() {
        Object sel = itemCombo.getValue();
        if (sel != null) {
            String p = (sel instanceof Salle s) ? s.getImagePath() : (sel instanceof Equipement eq ? eq.getImagePath() : null);
            if (p != null) try { imagePreview.setImage(new Image(p)); } catch(Exception ex){}
        }
    }

    private void setupItemComboDesign() {
        itemCombo.setCellFactory(lv -> new ListCell<Object>() {
            @Override
            protected void updateItem(Object item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setText(null);
                    setGraphic(null);
                } else {
                    // 1. Récupérer les dates saisies
                    LocalDateTime s = (startDatePicker.getValue() != null)
                            ? startDatePicker.getValue().atTime(8, 0) : LocalDateTime.now();
                    LocalDateTime e = (endDatePicker.getValue() != null)
                            ? endDatePicker.getValue().atTime(18, 0) : s.plusHours(2);

                    String name = "";
                    int dispo = 0;
                    int currentId = (selectedReservation == null) ? -1 : selectedReservation.getId();

                    // 2. Calculer la disponibilité selon le type
                    if (item instanceof Salle sa) {
                        name = sa.getName();
                        // Pour une salle : dispo = 1 si libre, 0 si occupée
                        dispo = resService.isSalleOccupee(sa.getId(), s, e, currentId) ? 0 : 1;
                    } else if (item instanceof Equipement eq) {
                        name = eq.getName();
                        // Pour un équipement : Total - Occupé
                        dispo = resService.getStockTotalEquipement(eq.getId()) - resService.getStockOccupe(eq.getId(), s, e, currentId);
                    }

                    // 3. Mise en forme visuelle
                    setText(name + " (Reste: " + dispo + ")");

                    if (dispo <= 0) {
                        setTextFill(Color.RED); // Rouge si épuisé
                        setDisable(true);       // Bloquer la sélection
                        setStyle("-fx-opacity: 0.5;"); // Effet grisé
                    } else {
                        setTextFill(Color.BLACK);
                        setDisable(false);
                        setStyle("-fx-opacity: 1.0;");
                    }
                }
            }
        });

        // Indispensable pour que l'affichage soit correct aussi quand le combo est fermé
        itemCombo.setButtonCell((ListCell) itemCombo.getCellFactory().call(null));
    }

    private void refreshItemCombo() { itemCombo.setCellFactory(null); setupItemComboDesign(); }
    @FXML void goBack() { MainController.getInstance().showReservations(); }
}