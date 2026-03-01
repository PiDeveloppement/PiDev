package com.example.pidev.controller.resource;

import com.example.pidev.MainController;
import com.example.pidev.model.resource.Equipement;
import com.example.pidev.service.resource.EquipementService;
import javafx.collections.FXCollections;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.stage.FileChooser;
import java.io.File;
import java.io.IOException;
import java.net.URL;
import java.util.ResourceBundle;


public class EquipementFormController implements Initializable {
    @FXML private TextField nameField, typeField, quantityField;
    @FXML private ComboBox<String> statusCombo;
    @FXML private Label titleLabel;
    @FXML private ImageView previewImage;

    private final EquipementService service = new EquipementService();
    private String currentImagePath = "";
    private int selectedId = -1;

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        statusCombo.setItems(FXCollections.observableArrayList("DISPONIBLE", "EN_PANNE"));
    }

    public void setEquipementData(Equipement e) {
        selectedId = e.getId();
        nameField.setText(e.getName());
        typeField.setText(e.getType());
        quantityField.setText(String.valueOf(e.getQuantity()));
        statusCombo.setValue(e.getStatus());
        currentImagePath = e.getImagePath();
        if (currentImagePath != null && !currentImagePath.isEmpty()) {
            previewImage.setImage(new Image(currentImagePath));
        }
        titleLabel.setText("Modifier l'Équipement");
    }

    /**
     * Méthode de validation des champs
     */
    private boolean estValide() {
        StringBuilder erreurs = new StringBuilder();

        if (nameField.getText().trim().isEmpty()) {
            erreurs.append("- Le nom est obligatoire.\n");
        }
        if (typeField.getText().trim().isEmpty()) {
            erreurs.append("- Le type est obligatoire.\n");
        }
        if (statusCombo.getValue() == null) {
            erreurs.append("- Veuillez sélectionner un statut.\n");
        }

        // Validation numérique pour la quantité
        try {
            int qte = Integer.parseInt(quantityField.getText());
            if (qte < 0) {
                erreurs.append("- La quantité ne peut pas être négative.\n");
            }
        } catch (NumberFormatException e) {
            erreurs.append("- La quantité doit être un nombre valide.\n");
        }

        if (erreurs.length() > 0) {
            Alert alert = new Alert(Alert.AlertType.WARNING);
            alert.setTitle("Erreur de saisie");
            alert.setHeaderText("Champs invalides");
            alert.setContentText(erreurs.toString());
            alert.showAndWait();
            return false;
        }

        return true;
    }
    @FXML
    void choisirImage() {
        FileChooser fc = new FileChooser();
        fc.getExtensionFilters().add(new FileChooser.ExtensionFilter("Images", "*.png", "*.jpg", "*.jpeg"));
        File selectedFile = fc.showOpenDialog(null);

        if (selectedFile != null) {
            try {
                // 1. Créer le dossier uploads à la racine du projet s'il n'existe pas
                File uploadDir = new File("uploads");
                if (!uploadDir.exists()) uploadDir.mkdir();

                // 2. Préparer le fichier de destination
                File destFile = new File(uploadDir.getPath() + "/" + selectedFile.getName());

                // 3. Copier le fichier (en utilisant les NIO de Java)
                java.nio.file.Files.copy(selectedFile.toPath(), destFile.toPath(),
                        java.nio.file.StandardCopyOption.REPLACE_EXISTING);

                // 4. Stocker le chemin ABSOLU pour la base de données
                currentImagePath = destFile.getAbsolutePath();

                // 5. Afficher l'aperçu
                previewImage.setImage(new Image(destFile.toURI().toString()));

            } catch (IOException e) {
                e.printStackTrace();
            }
        }
    }

    @FXML
    void enregistrer() {
        if (estValide()) { // On ne procède que si le contrôle de saisie passe
            try {
                Equipement e = new Equipement(
                        selectedId == -1 ? 0 : selectedId,
                        nameField.getText().trim(),
                        typeField.getText().trim(),
                        statusCombo.getValue(),
                        Integer.parseInt(quantityField.getText().trim()),
                        currentImagePath
                );

                if (selectedId == -1) {
                    service.ajouter(e);
                } else {
                    service.modifier(e);
                }

                annuler(); // Retour à la liste
            } catch (Exception ex) {
                ex.printStackTrace();
            }
        }
    }

    @FXML
    void annuler() {
        MainController.getInstance().loadEquipementsView();
    }
}