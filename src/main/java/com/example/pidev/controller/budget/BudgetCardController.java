package com.example.pidev.controller.budget;

import com.example.pidev.model.budget.Budget;
import javafx.fxml.FXML;
import javafx.scene.control.Button;
import javafx.scene.control.Label;

public class BudgetCardController {

    @FXML private Label titleLabel;
    @FXML private Label rentLabel;

    @FXML private Label idLabel;
    @FXML private Label eventLabel;

    @FXML private Label initialLabel;
    @FXML private Label expensesLabel;
    @FXML private Label revenueLabel;

    @FXML private Label statusLabel;

    // optionnel si tu gardes rent2Label dans FXML
    @FXML private Label rent2Label;

    @FXML private Button detailsBtn;
    @FXML private Button editBtn;
    @FXML private Button deleteBtn;

    public void setData(Budget b, Runnable onDetails, Runnable onEdit, Runnable onDelete) {
        if (b == null) return;

        if (titleLabel != null) titleLabel.setText("Budget");

        if (idLabel != null) idLabel.setText("ID: " + b.getId());
        if (eventLabel != null) eventLabel.setText("Event: " + b.getEvent_id());

        if (initialLabel != null) initialLabel.setText(String.format("%,.2f DT", b.getInitial_budget()));
        if (expensesLabel != null) expensesLabel.setText(String.format("%,.2f DT", b.getTotal_expenses()));
        if (revenueLabel != null) revenueLabel.setText(String.format("%,.2f DT", b.getTotal_revenue()));

        double rent = b.getRentabilite();

        if (rentLabel != null) {
            rentLabel.setText(String.format("%,.2f DT", rent));
            rentLabel.setStyle("-fx-font-size: 14px; -fx-font-weight: 900; -fx-text-fill:"
                    + (rent >= 0 ? "#059669" : "#dc2626") + ";");
        }

        if (rent2Label != null) {
            rent2Label.setText(String.format("%,.2f DT", rent));
            rent2Label.setStyle("-fx-font-weight: 900; -fx-text-fill:"
                    + (rent >= 0 ? "#059669" : "#dc2626") + ";");
        }

        if (statusLabel != null) {
            if (rent >= 0) {
                statusLabel.setText("OK");
                statusLabel.setStyle("-fx-background-color:#ecfdf5; -fx-text-fill:#047857; -fx-font-weight:900;"
                        + "-fx-padding:4 10; -fx-background-radius:999;");
            } else {
                statusLabel.setText("Déficit");
                statusLabel.setStyle("-fx-background-color:#fee2e2; -fx-text-fill:#b91c1c; -fx-font-weight:900;"
                        + "-fx-padding:4 10; -fx-background-radius:999;");
            }
        }

        if (detailsBtn != null) detailsBtn.setOnAction(e -> { if (onDetails != null) onDetails.run(); });
        if (editBtn != null) editBtn.setOnAction(e -> { if (onEdit != null) onEdit.run(); });
        if (deleteBtn != null) deleteBtn.setOnAction(e -> { if (onDelete != null) onDelete.run(); });
    }
}
