package com.example.pidev.controller.resource;

import com.example.pidev.MainController;
import com.example.pidev.model.resource.*;
import com.example.pidev.service.resource.*;
import com.example.pidev.utils.UserSession;

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
    @FXML private Label userInfoLabel;

    private final ReservationService resService = new ReservationService();
    private final SalleService salleService = new SalleService();
    private final EquipementService eqService = new EquipementService();
    private ReservationResource selectedReservation = null;
    private int currentUserId;
    private String currentUserName;

    @FXML
    public void initialize() {
        // Récupérer l'utilisateur connecté depuis la session
        UserSession session = UserSession.getInstance();
        currentUserId = session.getUserId();
        currentUserName = session.getFullName();

        // Afficher l'information utilisateur
        if (userInfoLabel != null) {
            if (session.isLoggedIn()) {
                userInfoLabel.setText("Réservation pour: " + currentUserName + " (ID: " + currentUserId + ")");
                userInfoLabel.setStyle("-fx-text-fill: #2c3e50; -fx-font-weight: bold;");
            } else {
                userInfoLabel.setText("⚠️ Aucun utilisateur connecté");
                userInfoLabel.setStyle("-fx-text-fill: red; -fx-font-weight: bold;");
            }
        }

        System.out.println("🔵 ReservationFormController - Utilisateur connecté: " + currentUserName + " (ID: " + currentUserId + ")");

        typeCombo.setItems(FXCollections.observableArrayList("SALLE", "EQUIPEMENT"));
        typeCombo.setOnAction(e -> chargerRessources());
        itemCombo.setOnAction(e -> mettreAJourApercu());
        startDatePicker.valueProperty().addListener((obs, oldV, newV) -> refreshItemCombo());
        endDatePicker.valueProperty().addListener((obs, oldV, newV) -> refreshItemCombo());
        setupItemComboDesign();

        // Validation de la quantité (uniquement des nombres)
        quantityField.textProperty().addListener((obs, oldVal, newVal) -> {
            if (!newVal.matches("\\d*")) {
                quantityField.setText(newVal.replaceAll("[^\\d]", ""));
            }
        });
    }

    public void setReservationToEdit(ReservationResource res) {
        this.selectedReservation = res;
        if (res != null) {
            typeCombo.setValue(res.getResourceType());
            chargerRessources();
            startDatePicker.valueProperty().addListener((obs, oldVal, newVal) -> {
                itemCombo.setCellFactory(null);
                setupItemComboDesign();
            });
            endDatePicker.valueProperty().addListener((obs, oldVal, newVal) -> {
                itemCombo.setCellFactory(null);
                setupItemComboDesign();
            });
            quantityField.setText(String.valueOf(res.getQuantity()));
            btnValider.setText("Mettre à jour");

            // Sélectionner l'élément correspondant
            for (Object obj : itemCombo.getItems()) {
                if (obj instanceof Salle s && s.getId() == res.getSalleId()) {
                    itemCombo.setValue(obj);
                    break;
                }
                if (obj instanceof Equipement eq && eq.getId() == res.getEquipementId()) {
                    itemCombo.setValue(obj);
                    break;
                }
            }

            // Afficher l'information de modification
            if (userInfoLabel != null) {
                userInfoLabel.setText("Modification réservation #" + res.getId() + " - " + currentUserName);
            }
        }
    }

    @FXML
    void validerAction() {
        try {
            // Vérifier que l'utilisateur est connecté
            if (currentUserId == -1) {
                new Alert(Alert.AlertType.ERROR,
                        "❌ Vous devez être connecté pour effectuer une réservation").show();
                return;
            }

            if (itemCombo.getValue() == null || startDatePicker.getValue() == null) {
                new Alert(Alert.AlertType.ERROR,
                        "Veuillez remplir tous les champs obligatoires").show();
                return;
            }

            LocalDateTime s = startDatePicker.getValue().atTime(8, 0);
            LocalDateTime e = endDatePicker.getValue() == null ?
                    s.plusHours(2) : endDatePicker.getValue().atTime(18, 0);

            Object sel = itemCombo.getValue();

            // Vérifier la quantité
            if (quantityField.getText().isEmpty()) {
                new Alert(Alert.AlertType.ERROR, "Veuillez saisir une quantité").show();
                return;
            }

            int qtySaisie = Integer.parseInt(quantityField.getText());
            if (qtySaisie <= 0) {
                new Alert(Alert.AlertType.ERROR, "La quantité doit être supérieure à 0").show();
                return;
            }

            int currentId = (selectedReservation == null) ? -1 : selectedReservation.getId();

            // Vérifier la disponibilité
            int dispo = 0;
            if (sel instanceof Salle sa) {
                dispo = resService.isSalleOccupee(sa.getId(), s, e, currentId) ? 0 : 1;
            } else if (sel instanceof Equipement eq) {
                dispo = resService.getStockTotalEquipement(eq.getId()) -
                        resService.getStockOccupe(eq.getId(), s, e, currentId);
            }

            if (qtySaisie > dispo) {
                new Alert(Alert.AlertType.ERROR,
                        "Stock insuffisant : " + dispo + " disponible(s)").show();
                return;
            }

            // Créer la réservation avec l'userId
            ReservationResource res = new ReservationResource(
                    currentId == -1 ? 0 : currentId,
                    typeCombo.getValue(),
                    (sel instanceof Salle sa) ? sa.getId() : null,
                    (sel instanceof Equipement eq) ? eq.getId() : null,
                    s, e, qtySaisie
            );

            // 🔥 IMPORTANT: Définir l'userId de l'utilisateur connecté
            res.setUserId(currentUserId);

            // Afficher un message de confirmation
            String message;
            if (selectedReservation == null) {
                resService.ajouter(res);
                message = "✅ Réservation créée avec succès pour " + currentUserName;
                System.out.println("Nouvelle réservation - UserID: " + currentUserId);
            } else {
                resService.modifier(res);
                message = "✅ Réservation modifiée avec succès";
                System.out.println("Modification réservation " + selectedReservation.getId() +
                        " - UserID: " + currentUserId);
            }

            // Confirmation visuelle
            Alert success = new Alert(Alert.AlertType.INFORMATION, message);
            success.setTitle("Succès");
            success.setHeaderText(null);
            success.showAndWait();

            goBack();

        } catch (NumberFormatException ex) {
            new Alert(Alert.AlertType.ERROR, "La quantité doit être un nombre valide").show();
            ex.printStackTrace();
        } catch (Exception ex) {
            ex.printStackTrace();
            new Alert(Alert.AlertType.ERROR, "Erreur: " + ex.getMessage()).show();
        }
    }

    private void chargerRessources() {
        itemCombo.getItems().clear();
        if ("SALLE".equals(typeCombo.getValue())) {
            itemCombo.setItems(FXCollections.observableArrayList(salleService.afficher()));
        } else if ("EQUIPEMENT".equals(typeCombo.getValue())) {
            itemCombo.setItems(FXCollections.observableArrayList(eqService.afficher()));
        }
    }

    private void mettreAJourApercu() {
        Object sel = itemCombo.getValue();
        if (sel != null) {
            String p = (sel instanceof Salle s) ? s.getImagePath() :
                    (sel instanceof Equipement eq ? eq.getImagePath() : null);
            if (p != null) {
                try {
                    imagePreview.setImage(new Image(p));
                } catch(Exception ex){
                    System.err.println("Erreur chargement image: " + p);
                }
            }
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
                    // Récupérer les dates saisies
                    LocalDateTime s = (startDatePicker.getValue() != null)
                            ? startDatePicker.getValue().atTime(8, 0) : LocalDateTime.now();
                    LocalDateTime e = (endDatePicker.getValue() != null)
                            ? endDatePicker.getValue().atTime(18, 0) : s.plusHours(2);

                    String name = "";
                    int dispo = 0;
                    int currentId = (selectedReservation == null) ? -1 : selectedReservation.getId();

                    // Calculer la disponibilité selon le type
                    if (item instanceof Salle sa) {
                        name = sa.getName();
                        dispo = resService.isSalleOccupee(sa.getId(), s, e, currentId) ? 0 : 1;
                    } else if (item instanceof Equipement eq) {
                        name = eq.getName();
                        dispo = resService.getStockTotalEquipement(eq.getId()) -
                                resService.getStockOccupe(eq.getId(), s, e, currentId);
                    }

                    // Mise en forme visuelle
                    if (dispo > 0) {
                        setText(name + " (Disponible: " + dispo + ")");
                        setTextFill(Color.BLACK);
                        setDisable(false);
                        setStyle("-fx-opacity: 1.0;");
                    } else {
                        setText(name + " (Indisponible)");
                        setTextFill(Color.RED);
                        setDisable(true);
                        setStyle("-fx-opacity: 0.5;");
                    }
                }
            }
        });

        // Important pour l'affichage quand le combo est fermé
        itemCombo.setButtonCell((ListCell) itemCombo.getCellFactory().call(null));
    }

    private void refreshItemCombo() {
        itemCombo.setCellFactory(null);
        setupItemComboDesign();
    }

    @FXML
    void goBack() {
        MainController.getInstance().showReservations();
    }
    // À AJOUTER dans votre ReservationFormController.java
    public void setCurrentUserId(int userId) {
        this.currentUserId = userId;
        System.out.println("🔵 UserId défini dans le formulaire: " + userId);

        // Optionnel: Mettre à jour l'affichage si vous avez un label
        if (userInfoLabel != null) {
            userInfoLabel.setText("Réservation pour utilisateur ID: " + userId);
        }
    }
}