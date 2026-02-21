package com.example.pidev.controller.budget;

import com.example.pidev.MainController;
import com.example.pidev.model.budget.Budget;
import com.example.pidev.service.budget.BudgetService;
import com.example.pidev.service.event.EventService;
import javafx.fxml.FXML;
import javafx.scene.control.ComboBox;
import javafx.scene.control.Label;
import javafx.scene.control.TextField;
import javafx.stage.Stage;

public class BudgetFormController {

    @FXML private Label titleLabel;
    @FXML private ComboBox<String> eventComboBox;
    @FXML private TextField initialField;
    @FXML private TextField revenueField;
    @FXML private Label errorLabel;

    private Budget current;
    private Budget result;

    private Runnable onFormDone;

    private final EventService eventService = new EventService();
    private final BudgetService budgetService = new BudgetService();

    public void setOnFormDone(Runnable callback) {
        this.onFormDone = callback;
    }

    public Budget getResult() {
        return result;
    }

    @FXML
    private void initialize() {
        loadEventTitles();
    }

    private void loadEventTitles() {
        try {
            if (eventComboBox != null) {
                eventComboBox.setItems(eventService.getAllEventTitles());
            }
        } catch (Exception e) {
            showError("Erreur chargement événements: " + e.getMessage());
        }
    }

    public void setModeAdd() {
        current = null;
        result = null;

        if (titleLabel != null) titleLabel.setText("➕ Nouveau Budget");
        loadEventTitles();

        if (eventComboBox != null) eventComboBox.setValue(null);
        if (initialField != null) initialField.clear();
        if (revenueField != null) revenueField.clear();
        if (errorLabel != null) errorLabel.setText("");
    }

    public void setModeEdit(Budget existing) {
        current = existing;
        result = null;

        if (titleLabel != null) titleLabel.setText("✏ Modifier Budget (ID: " + existing.getId() + ")");
        loadEventTitles();

        try {
            String eventTitle = eventService.getEventTitleById(existing.getEvent_id());
            if (eventComboBox != null) eventComboBox.setValue(eventTitle);
        } catch (Exception e) {
            showError("Erreur chargement événement.");
        }

        if (initialField != null) initialField.setText(String.valueOf(existing.getInitial_budget()));
        if (revenueField != null) revenueField.setText(String.valueOf(existing.getTotal_revenue()));
        if (errorLabel != null) errorLabel.setText("");
    }

    @FXML
    private void onCancel() {
        result = null;

        // ✅ si on est en page (pas de modal), revenir à la liste budget
        if (onFormDone != null) {
            onFormDone.run();
            return;
        }

        closeWindow();
    }

    @FXML
    private void onSave() {
        if (errorLabel != null) errorLabel.setText("");

        String selectedEventTitle = (eventComboBox == null) ? null : eventComboBox.getValue();
        int eventId;

        try {
            if (selectedEventTitle == null || selectedEventTitle.isEmpty()) {
                showError("Veuillez sélectionner un événement.");
                return;
            }
            eventId = eventService.getEventIdByTitle(selectedEventTitle);
        } catch (Exception e) {
            showError("Événement invalide: " + e.getMessage());
            return;
        }

        double initial;
        try {
            initial = Double.parseDouble(initialField.getText().trim().replace(',', '.'));
            if (initial < 0) { showError("Budget initial doit être >= 0."); return; }
        } catch (Exception e) {
            showError("Budget initial invalide (ex: 1000.00).");
            return;
        }

        double revenue;
        try {
            revenue = Double.parseDouble(revenueField.getText().trim().replace(',', '.'));
            if (revenue < 0) { showError("Revenus doit être >= 0."); return; }
        } catch (Exception e) {
            showError("Revenus invalide (ex: 500.00).");
            return;
        }

        Budget b = (current == null) ? new Budget() : current;
        b.setEvent_id(eventId);
        b.setInitial_budget(initial);
        b.setTotal_revenue(revenue);

        if (current == null) b.setTotal_expenses(0);

        try {
            if (current == null) budgetService.addBudget(b);
            else budgetService.updateBudget(b);

            result = b;

            // ✅ si on est en page : refresh liste + revenir
            if (onFormDone != null) {
                onFormDone.run();
                return;
            }

            closeWindow();

        } catch (Exception ex) {
            showError("Erreur sauvegarde: " + ex.getMessage());
        }
    }

    private void showError(String msg) {
        if (errorLabel != null) errorLabel.setText("❌ " + msg);
    }

    private void closeWindow() {
        try {
            if (eventComboBox != null && eventComboBox.getScene() != null) {
                Stage st = (Stage) eventComboBox.getScene().getWindow();
                if (st != null) st.close();
            }
        } catch (Exception ignored) {}
    }
}
