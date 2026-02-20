package com.example.pidev.controller.event;

import com.example.pidev.MainController;
import com.example.pidev.MainController;
import com.example.pidev.model.event.Event;
import com.example.pidev.model.event.EventCategory;
import com.example.pidev.service.event.EventService;
import com.example.pidev.service.event.EventCategoryService;
import javafx.animation.KeyFrame;
import javafx.animation.Timeline;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.layout.HBox;
import javafx.util.Duration;

import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.LocalTime;
import java.time.format.DateTimeFormatter;
import java.util.List;
import java.util.Locale;

/**
 * Controller pour le formulaire d'ajout/modification d'événement
 * @author Ons Abdesslem
 */
public class EventFormController {

    // ==================== FXML ELEMENTS ====================

    @FXML private Label titleLabel;
    @FXML private TextField titleField;
    @FXML private TextArea descriptionArea;
    @FXML private DatePicker startDatePicker;
    @FXML private Spinner<Integer> startHourSpinner;
    @FXML private Spinner<Integer> startMinuteSpinner;
    @FXML private DatePicker endDatePicker;
    @FXML private Spinner<Integer> endHourSpinner;
    @FXML private Spinner<Integer> endMinuteSpinner;
    @FXML private TextField locationField;
    @FXML private TextField capacityField;
    @FXML private TextField imageUrlField;
    @FXML private ComboBox<String> categoryCombo;
    @FXML private CheckBox freeCheckbox;
    @FXML private TextField priceField;
    @FXML private Button backBtn;
    @FXML private Button saveBtn;
    @FXML private Label titleError;
    @FXML private Label dateError;

    // ==================== NAVBAR ELEMENTS ====================
    @FXML private Label dateLabel;
    @FXML private Label timeLabel;

    // ==================== SERVICES ====================

    private EventService eventService;
    private EventCategoryService categoryService;
    private MainController helloController;
    private Event currentEvent;
    private List<EventCategory> allCategories;

    // ==================== INITIALIZATION ====================

    @FXML
    public void initialize() {
        System.out.println("✅ EventFormController initialisé");

        eventService = new EventService();
        categoryService = new EventCategoryService();

        loadCategories();
        setupListeners();
        setDefaultValues();

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

    private void loadCategories() {
        try {
            allCategories = categoryService.getAllCategories();

            categoryCombo.getItems().clear();

            if (allCategories != null) {
                for (EventCategory cat : allCategories) {
                    categoryCombo.getItems().add(cat.getName());
                }
            }

            configureCategoryComboCells();

            System.out.println("✅ " + (allCategories != null ? allCategories.size() : 0) + " catégories chargées");
        } catch (Exception e) {
            System.err.println("❌ Erreur chargement catégories: " + e.getMessage());
        }
    }

    private void configureCategoryComboCells() {
        categoryCombo.setCellFactory(listView -> new ListCell<>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setGraphic(null);
                    setText(null);
                    return;
                }

                EventCategory cat = findCategoryByName(item);
                if (cat != null) {
                    Label iconLabel = new Label(cat.getIcon() != null ? cat.getIcon() : "📌");
                    iconLabel.setStyle("-fx-font-size: 16px;");
                    Label nameLabel = new Label(cat.getName());
                    HBox box = new HBox(10, iconLabel, nameLabel);
                    box.setAlignment(Pos.CENTER_LEFT);
                    setGraphic(box);
                    setText(null);
                } else {
                    setText(item);
                }
            }
        });

        categoryCombo.setButtonCell(new ListCell<>() {
            @Override
            protected void updateItem(String item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) {
                    setText(null);
                    return;
                }

                EventCategory cat = findCategoryByName(item);
                if (cat != null) {
                    setText(cat.getIcon() + " " + cat.getName());
                } else {
                    setText(item);
                }
            }
        });
    }

    private EventCategory findCategoryByName(String name) {
        if (allCategories != null) {
            for (EventCategory cat : allCategories) {
                if (cat.getName().equals(name)) {
                    return cat;
                }
            }
        }
        return null;
    }

    private EventCategory findCategoryById(int id) {
        if (allCategories != null) {
            for (EventCategory cat : allCategories) {
                if (cat.getId() == id) {
                    return cat;
                }
            }
        }
        return null;
    }

    private void setupListeners() {
        // Activer/désactiver le champ prix selon le checkbox
        freeCheckbox.selectedProperty().addListener((obs, old, isFree) -> {
            priceField.setDisable(isFree);
            if (isFree) {
                priceField.setText("0");
            }
        });

        // ==================== VALIDATION EN TEMPS RÉEL ====================

        // Validation du titre (texte uniquement, max 200 caractères)
        titleField.textProperty().addListener((obs, oldVal, newVal) -> {
            if (titleError != null) {
                titleError.setVisible(false);
                titleField.setStyle(titleField.getStyle().replace("-fx-border-color: #ef4444;", ""));
            }

            if (newVal != null && newVal.length() > 200) {
                titleField.setText(oldVal);
                showTemporaryError(titleError, "❌ Maximum 200 caractères");
            }
        });

        // Validation de la capacité (nombres uniquement)
        capacityField.textProperty().addListener((obs, oldVal, newVal) -> {
            if (!newVal.matches("\\d*")) {
                capacityField.setText(newVal.replaceAll("[^\\d]", ""));
            }
            // Limiter à 6 chiffres maximum (999999)
            if (newVal.length() > 6) {
                capacityField.setText(oldVal);
            }
        });

        // Validation du prix (nombres décimaux uniquement)
        priceField.textProperty().addListener((obs, oldVal, newVal) -> {
            if (!newVal.matches("\\d*\\.?\\d*")) {
                priceField.setText(oldVal);
            }
            // Limiter à 10 caractères maximum (ex: 99999.99)
            if (newVal.length() > 10) {
                priceField.setText(oldVal);
            }
        });

        // Validation de la description (max 1000 caractères)
        descriptionArea.textProperty().addListener((obs, oldVal, newVal) -> {
            if (newVal != null && newVal.length() > 1000) {
                descriptionArea.setText(oldVal);
            }
        });

        // Validation du lieu (max 200 caractères)
        locationField.textProperty().addListener((obs, oldVal, newVal) -> {
            if (newVal != null && newVal.length() > 200) {
                locationField.setText(oldVal);
            }
        });
    }

    /**
     * Affiche temporairement un message d'erreur
     */
    private void showTemporaryError(Label errorLabel, String message) {
        if (errorLabel != null) {
            errorLabel.setText(message);
            errorLabel.setVisible(true);

            // Masquer après 3 secondes
            Timeline timeline = new Timeline(new KeyFrame(Duration.seconds(3), e -> {
                errorLabel.setVisible(false);
            }));
            timeline.play();
        }
    }

    private void setDefaultValues() {
        startDatePicker.setValue(LocalDate.now());
        endDatePicker.setValue(LocalDate.now().plusDays(1));

        // Valeurs par défaut pour les Spinner
        startHourSpinner.getValueFactory().setValue(9);       // 09:00
        startMinuteSpinner.getValueFactory().setValue(0);
        endHourSpinner.getValueFactory().setValue(17);        // 17:00
        endMinuteSpinner.getValueFactory().setValue(0);

        capacityField.setText("50");
        freeCheckbox.setSelected(true);
        priceField.setDisable(true);
        priceField.setText("0");

        if (!categoryCombo.getItems().isEmpty()) {
            categoryCombo.setValue(categoryCombo.getItems().get(0));
        }
    }

    public void setMainController(MainController helloController) {
        this.helloController = helloController;
    }

    public void setEvent(Event event) {
        this.currentEvent = event;

        if (event != null) {
            // Mode modification
            titleLabel.setText("Modifier l'Événement");
            saveBtn.setText("💾 Mettre à jour");

            titleField.setText(event.getTitle());
            descriptionArea.setText(event.getDescription());

            if (event.getStartDate() != null) {
                startDatePicker.setValue(event.getStartDate().toLocalDate());
                startHourSpinner.getValueFactory().setValue(event.getStartDate().getHour());
                startMinuteSpinner.getValueFactory().setValue(event.getStartDate().getMinute());
            }

            if (event.getEndDate() != null) {
                endDatePicker.setValue(event.getEndDate().toLocalDate());
                endHourSpinner.getValueFactory().setValue(event.getEndDate().getHour());
                endMinuteSpinner.getValueFactory().setValue(event.getEndDate().getMinute());
            }

            locationField.setText(event.getLocation());
            capacityField.setText(String.valueOf(event.getCapacity()));
            imageUrlField.setText(event.getImageUrl());

            EventCategory cat = findCategoryById(event.getCategoryId());
            if (cat != null) {
                categoryCombo.setValue(cat.getName());
            }

            freeCheckbox.setSelected(event.isFree());
            priceField.setText(String.valueOf(event.getTicketPrice()));
            priceField.setDisable(event.isFree());

            System.out.println("✏️ Mode modification: " + event.getTitle());
        } else {
            // Mode création
            titleLabel.setText("Nouvel Événement");
            saveBtn.setText("💾 Enregistrer");
            System.out.println("➕ Mode création");
        }
    }

    // ==================== VALIDATION ====================

    private boolean validateForm() {
        boolean isValid = true;

        // Réinitialiser les erreurs
        titleError.setVisible(false);
        dateError.setVisible(false);
        titleField.setStyle(titleField.getStyle().replace("-fx-border-color: #ef4444;", ""));
        startDatePicker.setStyle(startDatePicker.getStyle().replace("-fx-border-color: #ef4444;", ""));
        endDatePicker.setStyle(endDatePicker.getStyle().replace("-fx-border-color: #ef4444;", ""));

        // ==================== VALIDATION DU TITRE ====================
        String title = titleField.getText().trim();
        if (title.isEmpty()) {
            titleError.setText("❌ Le titre est obligatoire");
            titleError.setVisible(true);
            titleField.setStyle(titleField.getStyle() + "-fx-border-color: #ef4444; -fx-border-width: 2;");
            isValid = false;
        } else if (title.length() < 3) {
            titleError.setText("❌ Le titre doit contenir au moins 3 caractères");
            titleError.setVisible(true);
            titleField.setStyle(titleField.getStyle() + "-fx-border-color: #ef4444; -fx-border-width: 2;");
            isValid = false;
        } else if (title.length() > 200) {
            titleError.setText("❌ Le titre ne doit pas dépasser 200 caractères");
            titleError.setVisible(true);
            titleField.setStyle(titleField.getStyle() + "-fx-border-color: #ef4444; -fx-border-width: 2;");
            isValid = false;
        }

        // ==================== VALIDATION DE LA DESCRIPTION ====================
        String description = descriptionArea.getText().trim();
        if (description.isEmpty()) {
            showAlert("Erreur", "La description est obligatoire", Alert.AlertType.WARNING);
            isValid = false;
        } else if (description.length() < 10) {
            showAlert("Erreur", "La description doit contenir au moins 10 caractères", Alert.AlertType.WARNING);
            isValid = false;
        }

        // ==================== VALIDATION DES DATES ====================
        if (startDatePicker.getValue() == null) {
            dateError.setText("❌ La date de début est obligatoire");
            dateError.setVisible(true);
            startDatePicker.setStyle(startDatePicker.getStyle() + "-fx-border-color: #ef4444; -fx-border-width: 2;");
            isValid = false;
        }

        if (endDatePicker.getValue() == null) {
            dateError.setText("❌ La date de fin est obligatoire");
            dateError.setVisible(true);
            endDatePicker.setStyle(endDatePicker.getStyle() + "-fx-border-color: #ef4444; -fx-border-width: 2;");
            isValid = false;
        }

        // Vérifier que la date de fin est après la date de début
        if (startDatePicker.getValue() != null && endDatePicker.getValue() != null) {
            LocalDateTime startDateTime = LocalDateTime.of(
                startDatePicker.getValue(),
                LocalTime.of(startHourSpinner.getValue(), startMinuteSpinner.getValue())
            );
            LocalDateTime endDateTime = LocalDateTime.of(
                endDatePicker.getValue(),
                LocalTime.of(endHourSpinner.getValue(), endMinuteSpinner.getValue())
            );

            if (endDateTime.isBefore(startDateTime) || endDateTime.equals(startDateTime)) {
                dateError.setText("❌ La date de fin doit être après la date de début");
                dateError.setVisible(true);
                endDatePicker.setStyle(endDatePicker.getStyle() + "-fx-border-color: #ef4444; -fx-border-width: 2;");
                isValid = false;
            }
        }

        // ==================== VALIDATION DU LIEU ====================
        String location = locationField.getText().trim();
        if (location.isEmpty()) {
            showAlert("Erreur", "Le lieu est obligatoire", Alert.AlertType.WARNING);
            isValid = false;
        } else if (location.length() < 3) {
            showAlert("Erreur", "Le lieu doit contenir au moins 3 caractères", Alert.AlertType.WARNING);
            isValid = false;
        }

        // ==================== VALIDATION DE LA CAPACITÉ ====================
        try {
            int capacity = Integer.parseInt(capacityField.getText().trim());
            if (capacity <= 0) {
                showAlert("Erreur", "La capacité doit être supérieure à 0", Alert.AlertType.WARNING);
                isValid = false;
            } else if (capacity > 999999) {
                showAlert("Erreur", "La capacité ne peut pas dépasser 999999", Alert.AlertType.WARNING);
                isValid = false;
            }
        } catch (NumberFormatException e) {
            showAlert("Erreur", "La capacité doit être un nombre valide", Alert.AlertType.WARNING);
            isValid = false;
        }

        // ==================== VALIDATION DU PRIX ====================
        if (!freeCheckbox.isSelected()) {
            try {
                double price = Double.parseDouble(priceField.getText().trim());
                if (price <= 0) {
                    showAlert("Erreur", "Le prix doit être supérieur à 0", Alert.AlertType.WARNING);
                    isValid = false;
                }
            } catch (NumberFormatException e) {
                showAlert("Erreur", "Le prix doit être un nombre valide", Alert.AlertType.WARNING);
                isValid = false;
            }
        }

        // ==================== VALIDATION DE LA CATÉGORIE ====================
        if (categoryCombo.getValue() == null || categoryCombo.getValue().isEmpty()) {
            showAlert("Erreur", "Veuillez sélectionner une catégorie", Alert.AlertType.WARNING);
            isValid = false;
        }

        return isValid;
    }

    // ==================== ACTIONS ====================

    @FXML
    private void handleBack() {
        if (helloController != null) {
            helloController.showEventsList();
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

        String title = titleField.getText().trim();
        String description = descriptionArea.getText().trim();
        String location = locationField.getText().trim();
        int capacity = Integer.parseInt(capacityField.getText());
        String imageUrl = imageUrlField.getText().trim();

        String categoryName = categoryCombo.getValue();
        int categoryId = 1;
        EventCategory selectedCat = findCategoryByName(categoryName);
        if (selectedCat != null) {
            categoryId = selectedCat.getId();
        }

        boolean isFree = freeCheckbox.isSelected();
        double price = isFree ? 0 : Double.parseDouble(priceField.getText());

        // Créer les dates avec les Spinner
        LocalDateTime startDate = LocalDateTime.of(
                startDatePicker.getValue(),
                LocalTime.of(startHourSpinner.getValue(), startMinuteSpinner.getValue())
        );

        LocalDateTime endDate = LocalDateTime.of(
                endDatePicker.getValue(),
                LocalTime.of(endHourSpinner.getValue(), endMinuteSpinner.getValue())
        );

        // Vérifier que startDate < endDate
        if (startDate.isAfter(endDate) || startDate.isEqual(endDate)) {
            dateError.setText("❌ La date de fin doit être après la date de début");
            dateError.setVisible(true);
            return;
        }

        try {
            boolean success;

            if (currentEvent == null) {
                // Création
                Event newEvent = new Event(title, description, startDate, endDate,
                        location, capacity, imageUrl, categoryId, 1,
                        isFree, price);
                success = eventService.addEvent(newEvent);

                if (success) {
                    showSuccess("Succès", "Événement créé avec succès!");
                    handleBack();
                } else {
                    showError("Erreur", "Impossible de créer l'événement");
                }

            } else {
                // Modification
                currentEvent.setTitle(title);
                currentEvent.setDescription(description);
                currentEvent.setStartDate(startDate);
                currentEvent.setEndDate(endDate);
                currentEvent.setLocation(location);
                currentEvent.setCapacity(capacity);
                currentEvent.setImageUrl(imageUrl);
                currentEvent.setCategoryId(categoryId);
                currentEvent.setFree(isFree);
                currentEvent.setTicketPrice(price);

                success = eventService.updateEvent(currentEvent);

                if (success) {
                    showSuccess("Succès", "Événement mis à jour avec succès!");
                    handleBack();
                } else {
                    showError("Erreur", "Impossible de mettre à jour l'événement");
                }
            }

        } catch (Exception e) {
            System.err.println("❌ Erreur lors de la sauvegarde: " + e.getMessage());
            e.printStackTrace();
            showError("Erreur", "Une erreur est survenue: " + e.getMessage());
        }
    }

    // ==================== DIALOGS ====================

    private void showAlert(String title, String message, Alert.AlertType type) {
        Alert alert = new Alert(type);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

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
