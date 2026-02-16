package com.example.pidev.controller.budget;

import com.example.pidev.model.budget.Budget;
import javafx.fxml.FXML;
import javafx.scene.control.Label;
import javafx.scene.control.TextField;
import javafx.stage.Stage;

public class BudgetFormController {

    @FXML private Label titleLabel;
    @FXML private TextField eventIdField;
    @FXML private TextField initialField;
    @FXML private TextField revenueField;
    @FXML private Label errorLabel;

    private Budget current;
    private Budget result; // <= ce que le ListController récupère

    public void setModeAdd() {
        current = null;
        result = null;
        if (titleLabel != null) titleLabel.setText("➕ Nouveau Budget");
        if (eventIdField != null) eventIdField.clear();
        if (initialField != null) initialField.clear();
        if (revenueField != null) revenueField.clear();
        if (errorLabel != null) errorLabel.setText("");
    }

    public void setModeEdit(Budget existing) {
        current = existing;
        result = null;
        if (titleLabel != null) titleLabel.setText("✏ Modifier Budget (ID: " + existing.getId() + ")");
        if (eventIdField != null) eventIdField.setText(String.valueOf(existing.getEvent_id()));
        if (initialField != null) initialField.setText(String.valueOf(existing.getInitial_budget()));
        if (revenueField != null) revenueField.setText(String.valueOf(existing.getTotal_revenue()));
        if (errorLabel != null) errorLabel.setText("");
    }

    public Budget getResult() {
        return result;
    }

    @FXML
    private void onCancel() {
        result = null;
        close();
    }

    @FXML
    private void onSave() {
        if (errorLabel != null) errorLabel.setText("");

        int eventId;
        try {
            eventId = Integer.parseInt(eventIdField.getText().trim());
            if (eventId <= 0) { showError("ID événement doit être > 0."); return; }
        } catch (Exception e) {
            showError("ID événement invalide (ex: 1).");
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
        // expenses & rentabilite seront recalculés côté service (rentabilite = revenue - expenses)
        // on garde total_expenses tel quel (edit : ne pas casser la DB)
        if (current == null) b.setTotal_expenses(0);

        result = b;
        close();
    }

    private void showError(String msg) {
        if (errorLabel != null) errorLabel.setText("❌ " + msg);
    }

    private void close() {
        Stage st = (Stage) eventIdField.getScene().getWindow();
        st.close();
    }
}
