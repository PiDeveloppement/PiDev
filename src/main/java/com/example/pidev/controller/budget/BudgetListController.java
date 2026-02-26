package com.example.pidev.controller.budget;

import com.example.pidev.MainController;
import com.example.pidev.model.budget.Budget;
import com.example.pidev.service.budget.BudgetService;
import com.example.pidev.service.analytics.RoiCalculator;
import com.example.pidev.service.chart.QuickChartService;
import com.google.gson.JsonObject;
import javafx.collections.FXCollections;
import javafx.collections.ListChangeListener;
import javafx.collections.ObservableList;
import javafx.collections.transformation.FilteredList;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.fxml.Initializable;
import javafx.geometry.Insets;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.scene.layout.Region;
import javafx.scene.layout.TilePane;

import java.net.URL;
import java.time.LocalDate;
import java.time.format.DateTimeFormatter;
import java.util.*;
import java.util.stream.Collectors;

public class BudgetListController implements Initializable {

    @FXML private ScrollPane pageScroll;
    @FXML private Label kpiCountLabel;
    @FXML private Label kpiInitialLabel;
    @FXML private Label kpiRentLabel;
    @FXML private Label kpiDeficitLabel;
    @FXML private ComboBox<String> healthFilter;
    @FXML private ComboBox<String> eventFilter;
    @FXML private ComboBox<String> statusFilter;
    @FXML private Button addBtn;
    @FXML private Label statusLabel;
    @FXML private TilePane cardsPane;
    @FXML private ImageView budgetComparisonImage;
    @FXML private ImageView roiTrendImage;

    private final BudgetService budgetService = new BudgetService();
    private final ObservableList<Budget> baseList = FXCollections.observableArrayList();
    private FilteredList<Budget> filtered;

    private static final String CARD_FXML    = "/com/example/pidev/fxml/Budget/budget-card.fxml";
    private static final String FORM_FXML    = "/com/example/pidev/fxml/Budget/budget-form.fxml";
    private static final String DETAILS_FXML = "/com/example/pidev/fxml/Budget/budget-detail.fxml";

    @Override
    public void initialize(URL location, ResourceBundle resources) {

        filtered = new FilteredList<>(baseList, b -> true);
        filtered.addListener((ListChangeListener<Budget>) c -> renderCards());

        if (healthFilter != null) healthFilter.valueProperty().addListener((obs, o, n) -> applyPredicate());
        if (eventFilter  != null) eventFilter.valueProperty().addListener((obs, o, n) -> applyPredicate());
        if (statusFilter != null) statusFilter.valueProperty().addListener((obs, o, n) -> applyPredicate());

        if (addBtn != null) addBtn.setOnAction(e -> onAdd());

        if (cardsPane != null) {
            cardsPane.setPadding(new Insets(8));
            cardsPane.setHgap(14);
            cardsPane.setVgap(14);
        }

        if (pageScroll != null && cardsPane != null) {
            pageScroll.viewportBoundsProperty().addListener((obs, oldB, b) ->
                    cardsPane.setPrefColumns(computeCols(b.getWidth()))
            );
        }

        setupFiltersSafe();
        loadDataSafe();
        applyPredicate();
    }

    private int computeCols(double width) {
        if (width < 520) return 1;
        if (width < 980) return 2;
        return 3;
    }

    private void setupFiltersSafe() {
        if (healthFilter != null) {
            healthFilter.getItems().setAll("Tous", "🟢 Excellent", "🔵 Bon", "🟡 Fragile", "🔴 Critique");
            healthFilter.setValue("Tous");
        }
        if (statusFilter != null) {
            statusFilter.getItems().setAll("Tous", "OK", "Déficit");
            statusFilter.setValue("Tous");
        }
        if (eventFilter != null) {
            try {
                eventFilter.getItems().setAll(budgetService.getAllEventTitles());
            } catch (Exception e) {
                eventFilter.getItems().clear();
            }
            eventFilter.setValue(null);
        }
    }

    private void loadDataSafe() {
        try {
            baseList.setAll(budgetService.getAllBudgets());
            updateKpis();
            updateBudgetComparisonChart();
            updateRoiTrendChart();

            if (statusLabel != null) {
                statusLabel.setText("📊 " + baseList.size() + " budget(s) • Mise à jour: Maintenant");
            }

            renderCards();
        } catch (Exception e) {
            if (statusLabel != null) {
                statusLabel.setText("❌ Erreur chargement budgets: " + e.getMessage());
            }
        }
    }

    private void updateKpis() {
        if (kpiCountLabel   != null) kpiCountLabel.setText(String.valueOf(budgetService.countBudgets()));
        if (kpiInitialLabel != null) kpiInitialLabel.setText(String.format("%,.2f DT", budgetService.sumInitial()));
        if (kpiRentLabel    != null) kpiRentLabel.setText(String.format("%,.2f DT", budgetService.globalRentability()));
        if (kpiDeficitLabel != null) kpiDeficitLabel.setText(String.valueOf(budgetService.countDeficitBudgets()));
    }

    private void updateBudgetComparisonChart() {
        try {
            List<Budget> top5 = budgetService.getTop5Budgets();
            if (top5.isEmpty()) return;

            int n = top5.size();
            String[] labels = new String[n];
            double[] initial = new double[n];
            double[] expenses = new double[n];
            double[] revenue = new double[n];

            for (int i = 0; i < n; i++) {
                Budget b = top5.get(i);
                labels[i] = budgetService.getEventTitleById(b.getEvent_id());
                initial[i] = b.getInitial_budget();
                expenses[i] = b.getTotal_expenses();
                revenue[i] = b.getTotal_revenue();
            }

            String[] seriesNames = {"Budget initial", "Dépenses", "Revenus"};
            double[][] seriesData = {initial, expenses, revenue};

            JsonObject config = QuickChartService.createMultiBarChart(
                    "Comparaison des 5 derniers budgets",
                    labels,
                    seriesNames,
                    seriesData
            );

            String url = QuickChartService.getChartUrl(config);
            budgetComparisonImage.setImage(new Image(url, true));
        } catch (Exception e) {
            showError("Chart", "Erreur chargement graphique : " + e.getMessage());
        }
    }

    private void updateRoiTrendChart() {
        try {
            List<Budget> allBudgets = new ArrayList<>(baseList);
            if (allBudgets.size() < 2) return;

            List<RoiCalculator.Pair<LocalDate, Double>> dataPoints = new ArrayList<>();
            LocalDate baseDate = LocalDate.now().minusMonths(allBudgets.size());
            for (int i = 0; i < allBudgets.size(); i++) {
                Budget b = allBudgets.get(i);
                double roi = b.getRentabilite();
                LocalDate date = baseDate.plusMonths(i);
                dataPoints.add(new RoiCalculator.Pair<>(date, roi));
            }
            dataPoints.sort(Comparator.comparing(RoiCalculator.Pair::getFirst));

            RoiCalculator.LinearRegressionResult model = RoiCalculator.linearRegression(dataPoints);
            LocalDate minDate = dataPoints.stream().map(RoiCalculator.Pair::getFirst).min(LocalDate::compareTo).orElse(LocalDate.now());

            DateTimeFormatter fmt = DateTimeFormatter.ofPattern("yyyy-MM-dd");
            List<String> historicalLabels = new ArrayList<>();
            List<Double> historicalValues = new ArrayList<>();
            for (RoiCalculator.Pair<LocalDate, Double> point : dataPoints) {
                historicalLabels.add(point.getFirst().format(fmt));
                historicalValues.add(point.getSecond());
            }

            List<String> projectedLabels = new ArrayList<>();
            List<Double> projectedValues = new ArrayList<>();
            LocalDate lastDate = dataPoints.get(dataPoints.size() - 1).getFirst();
            for (int i = 1; i <= 3; i++) {
                LocalDate futureDate = lastDate.plusMonths(i);
                double predicted = model.predict(futureDate.toEpochDay() - minDate.toEpochDay());
                projectedLabels.add(futureDate.format(fmt));
                projectedValues.add(predicted);
            }

            List<String> allLabels = new ArrayList<>(historicalLabels);
            allLabels.addAll(projectedLabels);

            String[] seriesNames = {"ROI historique", "Projection"};
            double[][] seriesData = new double[2][allLabels.size()];
            for (int i = 0; i < historicalLabels.size(); i++) {
                seriesData[0][i] = historicalValues.get(i);
            }
            for (int i = 0; i < projectedLabels.size(); i++) {
                seriesData[1][historicalLabels.size() + i] = projectedValues.get(i);
            }

            JsonObject config = QuickChartService.createLineChart(
                    "Tendance du ROI avec projection sur 3 mois",
                    allLabels.toArray(new String[0]),
                    seriesNames,
                    seriesData
            );

            String url = QuickChartService.getChartUrl(config);
            roiTrendImage.setImage(new Image(url, true));
        } catch (Exception e) {
            showError("ROI Trend", "Erreur lors du calcul de la tendance : " + e.getMessage());
        }
    }

    private void applyPredicate() {
        if (filtered == null) return;

        String selectedHealth = (healthFilter == null || healthFilter.getValue() == null) ? "Tous" : healthFilter.getValue();
        String selectedEventTitle = (eventFilter == null) ? null : eventFilter.getValue();
        Integer eventId = null;
        if (selectedEventTitle != null && !selectedEventTitle.isEmpty()) {
            try {
                eventId = budgetService.getEventIdByTitle(selectedEventTitle);
            } catch (Exception ignored) {}
        }
        String st = (statusFilter == null || statusFilter.getValue() == null) ? "Tous" : statusFilter.getValue();
        Integer finalEventId = eventId;

        filtered.setPredicate(b -> {
            boolean okHealth = matchesHealth(b, selectedHealth);
            boolean okEv = (finalEventId == null) || b.getEvent_id() == finalEventId;
            boolean okStatus;
            if ("OK".equalsIgnoreCase(st)) okStatus = b.getRentabilite() >= 0;
            else if ("Déficit".equalsIgnoreCase(st)) okStatus = b.getRentabilite() < 0;
            else okStatus = true;
            return okHealth && okEv && okStatus;
        });

        if (statusLabel != null) {
            statusLabel.setText("📊 " + filtered.size() + " budget(s) filtrés • Mise à jour: Maintenant");
        }
        renderCards();
    }

    private String getFinancialHealth(Budget b) {
        if (b == null) return "Tous";
        double initial = b.getInitial_budget();
        double rent = b.getRentabilite();
        if (rent >= initial * 0.5) return "🟢 Excellent";
        if (rent >= 0) return "🔵 Bon";
        if (rent >= -initial * 0.2) return "🟡 Fragile";
        return "🔴 Critique";
    }

    private boolean matchesHealth(Budget b, String selectedHealth) {
        if ("Tous".equalsIgnoreCase(selectedHealth)) return true;
        return getFinancialHealth(b).contains(selectedHealth);
    }

    private void renderCards() {
        if (cardsPane == null || filtered == null) return;
        cardsPane.getChildren().clear();

        for (Budget b : filtered) {
            try {
                FXMLLoader loader = new FXMLLoader(getClass().getResource(CARD_FXML));
                Parent cardRoot = loader.load();
                if (cardRoot instanceof Region r) {
                    r.setPrefWidth(440);
                    r.setMaxWidth(Double.MAX_VALUE);
                }

                BudgetCardController cell = loader.getController();
                cell.setData(
                        b,
                        () -> openDetailsAsPage(b),
                        () -> onEdit(b),
                        () -> onDeleteNoIdText(b)
                );

                cardsPane.getChildren().add(cardRoot);
            } catch (Exception e) {
                showError("UI", "Erreur card budget: " + e.getMessage());
            }
        }
    }

    private void onAdd() {
        openFormAsPage(null);
    }

    private void onEdit(Budget existing) {
        if (existing == null) return;
        openFormAsPage(existing);
    }

    private void onDeleteNoIdText(Budget b) {
        String eventTitle;
        try {
            eventTitle = budgetService.getEventTitleById(b.getEvent_id());
        } catch (Exception e) {
            eventTitle = "cet événement";
        }

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Suppression");
        confirm.setHeaderText("Supprimer budget");
        confirm.setContentText("Voulez-vous supprimer le budget de : " + eventTitle + " ?");

        confirm.showAndWait().ifPresent(btn -> {
            if (btn == ButtonType.OK) {
                try {
                    budgetService.deleteBudget(b.getId());
                    setupFiltersSafe();
                    loadDataSafe();
                    applyPredicate();
                } catch (Exception ex) {
                    showError("Erreur suppression", ex.getMessage());
                }
            }
        });
    }

    private void openFormAsPage(Budget existing) {
        try {
            MainController.getInstance().loadIntoCenter(
                    FORM_FXML,
                    (BudgetFormController ctrl) -> {
                        if (existing == null) ctrl.setModeAdd();
                        else ctrl.setModeEdit(existing);
                        ctrl.setOnFormDone(() -> {
                            Budget saved = ctrl.getResult();
                            setupFiltersSafe();
                            loadDataSafe();
                            applyPredicate();
                            if (saved != null) {
                                openDetailsAsPage(saved);
                            } else {
                                MainController.getInstance().onBudget();
                            }
                        });
                    }
            );
        } catch (Exception e) {
            showError("UI", "Impossible d'ouvrir le formulaire: " + e.getMessage());
        }
    }

    private void openDetailsAsPage(Budget b) {
        try {
            MainController.getInstance().loadIntoCenter(
                    DETAILS_FXML,
                    (BudgetDetailsController ctrl) -> {
                        ctrl.setBudget(b);
                        ctrl.setOnCloseAction(() -> MainController.getInstance().onBudget());
                    }
            );
        } catch (Exception e) {
            showError("Détails", "Impossible d'ouvrir les détails: " + e.getMessage());
        }
    }

    private void showError(String title, String msg) {
        Alert a = new Alert(Alert.AlertType.ERROR);
        a.setTitle(title);
        a.setHeaderText(null);
        a.setContentText(msg);
        a.showAndWait();
    }
}