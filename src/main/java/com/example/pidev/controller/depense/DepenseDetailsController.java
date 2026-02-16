package com.example.pidev.controller.depense;

import com.example.pidev.model.depense.Depense;
import javafx.fxml.FXML;
import javafx.scene.control.Label;
import javafx.stage.Stage;

import java.time.format.DateTimeFormatter;

public class DepenseDetailsController {

    @FXML private Label idLabel;
    @FXML private Label budgetIdLabel;
    @FXML private Label descLabel;
    @FXML private Label categoryLabel;
    @FXML private Label amountLabel;
    @FXML private Label dateLabel;

    // si tu as mis ces labels dans FXML
    @FXML private Label statusLabel;
    @FXML private Label createdByLabel;
    @FXML private Label validatedByLabel;

    private static final DateTimeFormatter FMT = DateTimeFormatter.ofPattern("yyyy-MM-dd");

    public void setDepense(Depense d) {
        if (d == null) return;

        if (idLabel != null) idLabel.setText(String.valueOf(d.getId()));
        if (budgetIdLabel != null) budgetIdLabel.setText(String.valueOf(d.getBudget_id()));
        if (descLabel != null) descLabel.setText(nullSafe(d.getDescription()));
        if (categoryLabel != null) categoryLabel.setText(nullSafe(d.getCategory()));

        if (amountLabel != null) amountLabel.setText(String.format("%,.2f DT", d.getAmount()));
        if (dateLabel != null) dateLabel.setText(d.getExpense_date() == null ? "—" : FMT.format(d.getExpense_date()));

        // Ton modèle n'a pas status/createdBy/validatedBy => on met —
        if (statusLabel != null) statusLabel.setText("—");
        if (createdByLabel != null) createdByLabel.setText("—");
        if (validatedByLabel != null) validatedByLabel.setText("—");
    }

    @FXML
    private void onClose() {
        Stage st = (Stage) idLabel.getScene().getWindow();
        st.close();
    }

    private String nullSafe(String s) {
        return (s == null || s.isBlank()) ? "—" : s;
    }
}
