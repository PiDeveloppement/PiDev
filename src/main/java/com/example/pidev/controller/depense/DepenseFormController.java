package com.example.pidev.controller.depense;

import com.example.pidev.model.depense.Depense;
import javafx.fxml.FXML;
import javafx.scene.control.*;
import javafx.stage.Stage;
import javafx.util.StringConverter;

import java.time.LocalDate;
import java.time.format.DateTimeFormatter;

public class DepenseFormController {

    @FXML private Label titleLabel;
    @FXML private TextField budgetIdField;
    @FXML private TextField descField;
    @FXML private TextField categoryField;
    @FXML private TextField amountField;
    @FXML private DatePicker datePicker;
    @FXML private Label errorLabel;

    private Depense depense;
    private boolean saved = false;

    private static final DateTimeFormatter DB_FMT = DateTimeFormatter.ofPattern("yyyy-MM-dd");

    @FXML
    private void initialize() {
        if (datePicker != null) {
            datePicker.setConverter(new StringConverter<>() {
                @Override public String toString(LocalDate date) {
                    return date == null ? "" : DB_FMT.format(date);
                }
                @Override public LocalDate fromString(String s) {
                    if (s == null || s.trim().isEmpty()) return null;
                    return LocalDate.parse(s.trim(), DB_FMT);
                }
            });
            datePicker.setEditable(false);
        }
        if (errorLabel != null) errorLabel.setText("");
    }

    public void setDepense(Depense existing) {
        this.depense = existing;

        if (existing == null) {
            if (titleLabel != null) titleLabel.setText("➕ Nouvelle Dépense");
            if (budgetIdField != null) budgetIdField.clear();
            if (descField != null) descField.clear();
            if (categoryField != null) categoryField.clear();
            if (amountField != null) amountField.clear();
            if (datePicker != null) datePicker.setValue(LocalDate.now());
            return;
        }

        if (titleLabel != null) titleLabel.setText("✏ Modifier Dépense (ID: " + existing.getId() + ")");
        if (budgetIdField != null) budgetIdField.setText(String.valueOf(existing.getBudget_id()));
        if (descField != null) descField.setText(existing.getDescription());
        if (categoryField != null) categoryField.setText(existing.getCategory());
        if (amountField != null) amountField.setText(String.valueOf(existing.getAmount()));
        if (datePicker != null) datePicker.setValue(existing.getExpense_date());
    }

    public boolean isSaved() { return saved; }
    public Depense getDepense() { return depense; }

    @FXML
    private void onCancel() {
        saved = false;
        closeWindow();
    }

    @FXML
    private void onSave() {
        clearError();

        int budgetId;
        try {
            budgetId = Integer.parseInt(budgetIdField.getText().trim());
            if (budgetId <= 0) { showError("ID Budget doit être > 0."); return; }
        } catch (Exception e) {
            showError("ID Budget doit être un entier (ex: 1).");
            return;
        }

        String desc = descField.getText() == null ? "" : descField.getText().trim();
        if (desc.isEmpty()) { showError("Description obligatoire."); return; }

        String cat = categoryField.getText() == null ? "" : categoryField.getText().trim();
        if (cat.isEmpty()) { showError("Catégorie obligatoire."); return; }

        double amount;
        try {
            String raw = amountField.getText().trim().replace(',', '.');
            amount = Double.parseDouble(raw);
            if (amount <= 0) { showError("Montant doit être > 0."); return; }
        } catch (Exception e) {
            showError("Montant invalide (ex: 150.00).");
            return;
        }

        LocalDate dt = datePicker.getValue();
        if (dt == null) { showError("Veuillez choisir une date."); return; }

        if (depense == null) depense = new Depense();
        depense.setBudget_id(budgetId);
        depense.setDescription(desc);
        depense.setCategory(cat);
        depense.setAmount(amount);
        depense.setExpense_date(dt);

        saved = true;
        closeWindow();
    }

    private void closeWindow() {
        Stage stage = (Stage) budgetIdField.getScene().getWindow();
        stage.close();
    }

    private void showError(String msg) {
        if (errorLabel != null) errorLabel.setText("❌ " + msg);
    }

    private void clearError() {
        if (errorLabel != null) errorLabel.setText("");
    }
}
