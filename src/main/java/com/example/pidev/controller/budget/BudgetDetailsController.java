package com.example.pidev.controller.budget;

import com.example.pidev.model.budget.Budget;
import com.example.pidev.model.depense.Depense;
import com.example.pidev.service.budget.BudgetService;
import com.example.pidev.service.depense.DepenseService;
import com.example.pidev.service.forecast.EconomicForecastService;
import javafx.fxml.FXML;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.layout.HBox;

import java.time.LocalDate;
import java.time.temporal.ChronoUnit;
import java.util.List;
import java.util.Objects;
import java.util.Locale;

public class BudgetDetailsController {

    @FXML private Label eventTitleLabel;
    @FXML private Label initialLabel;
    @FXML private Label expensesLabel;
    @FXML private Label revenueLabel;
    @FXML private Label rentLabel;
    @FXML private Label statusLabel;
    @FXML private Label deficitAmountLabel;
    @FXML private Label dailyRateLabel;
    @FXML private Label doubleDaysLabel;
    @FXML private Label exchangeRateLabel;
    @FXML private HBox deficitBox;
    @FXML private HBox rateBox;
    @FXML private HBox doubleBox;
    @FXML private HBox exchangeBox;
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
        double remaining = initial - totalExpenses; // peut être négatif

        List<Depense> depenses = depenseService.getDepensesByBudgetId(currentBudget.getId());

        // Calcul du rythme journalier si possible
        double avgDaily = -1;
        if (!depenses.isEmpty()) {
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
                    avgDaily = totalExpenses / daysSpan;
                }
            }
        }

        // Mise à jour des cadres de prévision
        if (remaining >= 0) {
            // Budget non dépassé
            deficitBox.setVisible(false);
            if (avgDaily > 0 && remaining > 0) {
                long daysLeft = (long) (remaining / avgDaily);
                dailyRateLabel.setText(String.format("%.2f DT/jour", avgDaily));
                doubleDaysLabel.setText(daysLeft + " jours (avant épuisement)");
                rateBox.setVisible(true);
                doubleBox.setVisible(true);
            } else {
                rateBox.setVisible(false);
                doubleBox.setVisible(false);
            }
        } else {
            // Budget dépassé
            double deficit = -remaining;
            deficitAmountLabel.setText(String.format("%,.2f DT", deficit));
            deficitBox.setVisible(true);

            if (avgDaily > 0) {
                dailyRateLabel.setText(String.format("%.2f DT/jour", avgDaily));
                double exactDays = deficit / avgDaily;
                if (exactDays < 1) {
                    doubleDaysLabel.setText("< 1 jour");
                } else {
                    long daysToDouble = (long) exactDays;
                    doubleDaysLabel.setText(daysToDouble + " jours (doublement)");
                }
                rateBox.setVisible(true);
                doubleBox.setVisible(true);
            } else {
                rateBox.setVisible(false);
                doubleBox.setVisible(false);
            }
        }

        // Taux de change (API)
        double usdToTnd = forecastService.getExchangeRate("USD", "TND");
        if (usdToTnd > 0) {
            exchangeRateLabel.setText(String.format("1 USD = %.4f TND", usdToTnd));
            exchangeBox.setVisible(true);
        } else {
            exchangeBox.setVisible(false);
        }
    }

    @FXML
    private void onClose() {
        if (onCloseAction != null) onCloseAction.run();
    }
}