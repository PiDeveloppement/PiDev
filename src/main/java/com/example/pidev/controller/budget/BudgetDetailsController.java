package com.example.pidev.controller.budget;

import com.example.pidev.model.budget.Budget;
import com.example.pidev.model.depense.Depense;
import com.example.pidev.service.budget.BudgetService;
import com.example.pidev.service.depense.DepenseService;
import com.example.pidev.service.forecast.EconomicForecastService;
import javafx.fxml.FXML;
import javafx.scene.control.Button;
import javafx.scene.control.Label;

import java.time.LocalDate;
import java.time.temporal.ChronoUnit;
import java.util.List;
import java.util.Objects;

public class BudgetDetailsController {

    @FXML private Label eventTitleLabel;
    @FXML private Label initialLabel;
    @FXML private Label expensesLabel;
    @FXML private Label revenueLabel;
    @FXML private Label rentLabel;
    @FXML private Label statusLabel;
    @FXML private Label forecastLabel;
    @FXML private Label adjustedForecastLabel;
    @FXML private Button closeBtn;

    private Budget currentBudget;
    private Runnable onCloseAction;

    private final BudgetService budgetService = new BudgetService();
    private final DepenseService depenseService = new DepenseService();
    private final EconomicForecastService forecastService = new EconomicForecastService();

    public void setBudget(Budget budget) {
        this.currentBudget = budget;
        displayBudget();
    }

    public void setOnCloseAction(Runnable action) {
        this.onCloseAction = action;
    }

    private void displayBudget() {
        if (currentBudget == null) return;

        String eventTitle = budgetService.getEventTitleById(currentBudget.getEvent_id());
        eventTitleLabel.setText(eventTitle);

        initialLabel.setText(String.format("%,.2f DT", currentBudget.getInitial_budget()));
        expensesLabel.setText(String.format("%,.2f DT", currentBudget.getTotal_expenses()));
        revenueLabel.setText(String.format("%,.2f DT", currentBudget.getTotal_revenue()));

        double rent = currentBudget.getRentabilite();
        rentLabel.setText(String.format("%,.2f DT", rent));
        if (rent >= 0) {
            statusLabel.setText("✓ Rentable");
            statusLabel.setStyle("-fx-font-size: 14px; -fx-text-fill: #16a34a; -fx-font-weight: bold; " +
                    "-fx-background-color: #d1fae5; -fx-background-radius: 8; -fx-padding: 8 12;");
        } else {
            statusLabel.setText("Déficit");
            statusLabel.setStyle("-fx-font-size: 14px; -fx-text-fill: #b91c1c; -fx-font-weight: bold; " +
                    "-fx-background-color: #fee2e2; -fx-background-radius: 8; -fx-padding: 8 12;");
        }

        double initial = currentBudget.getInitial_budget();
        double totalExpenses = currentBudget.getTotal_expenses();
        double remaining = initial - totalExpenses;

        List<Depense> depenses = depenseService.getDepensesByBudgetId(currentBudget.getId());
        long daysLeft = -1;

        if (!depenses.isEmpty() && remaining > 0) {
            LocalDate earliest = depenses.stream()
                    .map(Depense::getExpense_date)
                    .filter(Objects::nonNull)
                    .min(LocalDate::compareTo)
                    .orElse(null);
            LocalDate latest = depenses.stream()
                    .map(Depense::getExpense_date)
                    .filter(Objects::nonNull)
                    .max(LocalDate::compareTo)
                    .orElse(null);

            if (earliest != null && latest != null && !earliest.equals(latest)) {
                long daysSpan = ChronoUnit.DAYS.between(earliest, latest);
                if (daysSpan > 0) {
                    double avgDaily = totalExpenses / daysSpan;
                    if (avgDaily > 0) {
                        daysLeft = (long) (remaining / avgDaily);
                        forecastLabel.setText("📅 Budget tient encore ~" + daysLeft + " jours au rythme actuel");
                    } else {
                        forecastLabel.setText("💰 Budget restant : " + String.format("%,.2f DT", remaining));
                    }
                } else {
                    forecastLabel.setText("💰 Budget restant : " + String.format("%,.2f DT", remaining));
                }
            } else {
                forecastLabel.setText("💰 Budget restant : " + String.format("%,.2f DT", remaining));
            }
        } else {
            if (remaining >= 0) {
                forecastLabel.setText("💰 Budget restant : " + String.format("%,.2f DT", remaining));
            } else {
                forecastLabel.setText("🚨 Dépassement de " + String.format("%,.2f DT", -remaining) + " par rapport au budget");
            }
        }

        if (daysLeft > 0 && remaining > 0) {
            double adjustedRemaining = forecastService.adjustForInflation(remaining, (int) daysLeft, "TND");
            double usdToTnd = forecastService.getExchangeRate("USD", "TND");
            String adjustedText = String.format("Ajusté (inflation estimée) : ~%,.2f DT", adjustedRemaining);
            if (usdToTnd > 0) {
                adjustedText += String.format("  (1 USD = %.4f TND)", usdToTnd);
            }
            adjustedForecastLabel.setText(adjustedText);
            adjustedForecastLabel.setStyle("-fx-text-fill: #2563eb; -fx-font-size: 12px;");
        } else {
            adjustedForecastLabel.setText("");
        }
    }

    @FXML
    private void onClose() {
        if (onCloseAction != null) onCloseAction.run();
    }
}