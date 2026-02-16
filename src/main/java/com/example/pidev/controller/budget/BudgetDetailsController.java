package com.example.pidev.controller.budget;

import com.example.pidev.model.budget.Budget;
import javafx.fxml.FXML;
import javafx.scene.control.Label;

public class BudgetDetailsController {

    @FXML private Label idLabel;
    @FXML private Label eventIdLabel;

    @FXML private Label initialLabel;
    @FXML private Label expensesLabel;
    @FXML private Label revenueLabel;
    @FXML private Label rentLabel;

    public void setBudget(Budget b) {
        if (b == null) return;

        if (idLabel != null) idLabel.setText(String.valueOf(b.getId()));
        if (eventIdLabel != null) eventIdLabel.setText(String.valueOf(b.getEvent_id()));

        if (initialLabel != null) initialLabel.setText(String.format("%,.2f DT", b.getInitial_budget()));
        if (expensesLabel != null) expensesLabel.setText(String.format("%,.2f DT", b.getTotal_expenses()));
        if (revenueLabel != null) revenueLabel.setText(String.format("%,.2f DT", b.getTotal_revenue()));

        double rent = b.getRentabilite();
        if (rentLabel != null) {
            rentLabel.setText(String.format("%,.2f DT", rent));
            rentLabel.setStyle("-fx-font-weight: 900; -fx-text-fill: " + (rent >= 0 ? "#059669" : "#dc2626") + ";");
        }
    }
}
