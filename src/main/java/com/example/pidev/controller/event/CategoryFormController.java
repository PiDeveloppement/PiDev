package com.example.pidev.controller.event;

import com.example.pidev.HelloController;
import com.example.pidev.model.event.EventCategory;
import com.example.pidev.service.event.EventCategoryService;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.scene.layout.Region;
import javafx.scene.paint.Color;
import javafx.util.Duration;

import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.Locale;

/**
 * Controller pour le formulaire d'ajout/modification de catégorie
 *
 * @author Ons Abdesslem
 * @version 4.0 - Avec aperçu dynamique corrigé
 */
public class CategoryFormController {

    // ==================== FXML ELEMENTS ====================

    @FXML private Label titleLabel;
    @FXML private TextField nameField;
    @FXML private TextArea descriptionArea;
    @FXML private ComboBox<String> iconCombo;
    @FXML private ColorPicker colorPicker;
    @FXML private TextField colorField;
    @FXML private RadioButton activeRadio;
    @FXML private RadioButton inactiveRadio;
    @FXML private ToggleGroup statusGroup;
    @FXML private Button backBtn;
    @FXML private Button saveBtn;
    @FXML private Label nameError;

    // ==================== APERÇU DYNAMIQUE ====================
    @FXML private Label iconPreview;
    @FXML private Region colorPreview;

    // ==================== NAVBAR ELEMENTS ====================
    @FXML private Label dateLabel;
    @FXML private Label timeLabel;

    // ==================== SERVICES ====================

    private EventCategoryService categoryService;
    private HelloController helloController;
    private EventCategory currentCategory;


    // ==================== INITIALIZATION ====================

    @FXML
    public void initialize() {
        System.out.println("✅ CategoryFormController initialisé");

        categoryService = new EventCategoryService();

        setupIconComboBox();
        setupColorPicker();
        setupPreviewListeners();

        // Initialiser le ToggleGroup pour les RadioButtons
        if (statusGroup == null) {
            statusGroup = new ToggleGroup();
            activeRadio.setToggleGroup(statusGroup);
            inactiveRadio.setToggleGroup(statusGroup);
        }

        // Sélectionner Actif par défaut
        if (statusGroup.getSelectedToggle() == null) {
            activeRadio.setSelected(true);
        }

        // Initialiser la date et l'heure
        updateDateTime();

        // Mettre à jour l'heure chaque seconde
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

    public void setCategory(EventCategory category) {
        this.currentCategory = category;

        if (category != null) {
            // Mode modification
            titleLabel.setText("Modifier la Catégorie");
            saveBtn.setText("💾 Mettre à jour");

            nameField.setText(category.getName());
            descriptionArea.setText(category.getDescription());
            iconCombo.setValue(category.getIcon());
            iconPreview.setText(category.getIcon() != null ? category.getIcon() : "📌");

            if (category.isActive()) {
                activeRadio.setSelected(true);
            } else {
                inactiveRadio.setSelected(true);
            }

            if (category.getColor() != null && !category.getColor().isEmpty()) {
                try {
                    Color color = Color.web(category.getColor());
                    colorPicker.setValue(color);
                } catch (IllegalArgumentException e) {
                    colorPicker.setValue(Color.web("#2196F3"));
                }
            }

            System.out.println("✏️ Mode modification: " + category.getName());
        } else {
            // Mode création
            titleLabel.setText("Nouvelle Catégorie");
            saveBtn.setText("💾 Enregistrer");

            colorPicker.setValue(Color.web("#2196F3"));
            iconCombo.setValue("📌");
            iconPreview.setText("📌");
            activeRadio.setSelected(true);

            System.out.println("➕ Mode création");
        }

        // Mettre à jour le champ couleur et l'aperçu
        updateColorField();
        updateColorPreview();
    }


    // ==================== SETUP ====================

    private void setupIconComboBox() {
        iconCombo.getItems().addAll(
                "📌", "🎓", "🛠️", "🏆", "🎭", "💼", "⚽", "🎨",
                "🎵", "🍔", "📚", "💻", "🎯", "🌍", "🎪", "🎬",
                "📸", "🎤", "🏀", "🎸", "🎮", "✈️", "🏋️", "🧘"
        );

        if (iconCombo.getValue() == null) {
            iconCombo.setValue("📌");
        }
    }

    private void setupColorPicker() {
        colorPicker.setValue(Color.web("#2196F3"));
        updateColorField();
        updateColorPreview();

        colorPicker.setOnAction(e -> {
            updateColorField();
            updateColorPreview();
        });
    }

    private void setupPreviewListeners() {
        // Mettre à jour l'aperçu de l'icône
        iconCombo.valueProperty().addListener((obs, oldVal, newVal) -> {
            if (newVal != null) {
                iconPreview.setText(newVal);
                System.out.println("Icône changée: " + newVal);
            }
        });
    }


    // ==================== COULEUR ====================

    private void updateColorField() {
        Color color = colorPicker.getValue();
        String hex = String.format("#%02X%02X%02X",
                (int) (color.getRed() * 255),
                (int) (color.getGreen() * 255),
                (int) (color.getBlue() * 255)
        );
        colorField.setText(hex);
    }

    private void updateColorPreview() {
        Color color = colorPicker.getValue();
        if (color != null) {
            String hex = String.format("#%02X%02X%02X",
                    (int) (color.getRed() * 255),
                    (int) (color.getGreen() * 255),
                    (int) (color.getBlue() * 255)
            );

            // Mettre à jour uniquement la couleur de fond, garder le reste
            colorPreview.setStyle(
                    "-fx-min-width: 30; " +
                            "-fx-min-height: 30; " +
                            "-fx-background-radius: 6; " +
                            "-fx-background-color: " + hex + "; " +
                            "-fx-border-color: #ced4da; " +
                            "-fx-border-width: 1; " +
                            "-fx-border-radius: 6;"
            );

            System.out.println("Aperçu couleur mis à jour: " + hex);
        }
    }


    // ==================== VALIDATION ====================

    private boolean validateForm() {
        boolean isValid = true;

        nameError.setVisible(false);
        nameField.setStyle(nameField.getStyle().replace("-fx-border-color: #ef4444;", ""));

        String name = nameField.getText().trim();
        if (name.isEmpty()) {
            nameError.setText("❌ Le nom est obligatoire");
            nameError.setVisible(true);
            nameField.setStyle(nameField.getStyle() + "-fx-border-color: #ef4444; -fx-border-width: 2;");
            isValid = false;
        } else if (name.length() > 100) {
            nameError.setText("❌ Le nom ne doit pas dépasser 100 caractères");
            nameError.setVisible(true);
            nameField.setStyle(nameField.getStyle() + "-fx-border-color: #ef4444; -fx-border-width: 2;");
            isValid = false;
        }

        return isValid;
    }


    // ==================== ACTIONS ====================

    @FXML
    private void handleBack() {
        if (helloController != null) {
            helloController.showCategories();
        }
    }

    @FXML
    private void handleCancel() {
        Alert confirmation = new Alert(Alert.AlertType.CONFIRMATION);
        confirmation.setTitle("Confirmation");
        confirmation.setHeaderText("Annuler les modifications?");
        confirmation.setContentText("Les modifications non enregistrées seront perdues.");

        confirmation.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                handleBack();
            }
        });
    }

    @FXML
    private void handleSave() {
        if (!validateForm()) {
            return;
        }

        String name = nameField.getText().trim();
        String description = descriptionArea.getText().trim();
        String icon = iconCombo.getValue();
        String color = colorField.getText();
        boolean isActive = activeRadio.isSelected();

        try {
            boolean success;

            if (currentCategory == null) {
                // Création
                EventCategory newCategory = new EventCategory(name, description, icon, color, isActive);
                success = categoryService.addCategory(newCategory);

                if (success) {
                    showSuccess("Succès", "Catégorie créée avec succès!");
                    handleBack();
                } else {
                    showError("Erreur", "Impossible de créer la catégorie (nom déjà existant?)");
                }

            } else {
                // Modification
                currentCategory.setName(name);
                currentCategory.setDescription(description);
                currentCategory.setIcon(icon);
                currentCategory.setColor(color);
                currentCategory.setActive(isActive);

                success = categoryService.updateCategory(currentCategory);

                if (success) {
                    showSuccess("Succès", "Catégorie mise à jour avec succès!");
                    handleBack();
                } else {
                    showError("Erreur", "Impossible de mettre à jour la catégorie");
                }
            }

        } catch (Exception e) {
            System.err.println("❌ Erreur lors de la sauvegarde: " + e.getMessage());
            e.printStackTrace();
            showError("Erreur", "Une erreur est survenue: " + e.getMessage());
        }
    }


    // ==================== DIALOGS ====================

    private void showError(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.ERROR);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    private void showSuccess(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }
}
