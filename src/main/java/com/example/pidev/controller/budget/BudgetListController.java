package com.example.pidev.controller.budget;

import com.example.pidev.model.budget.Budget;
import com.example.pidev.service.budget.BudgetService;
import javafx.collections.FXCollections;
import javafx.collections.ListChangeListener;
import javafx.collections.ObservableList;
import javafx.collections.transformation.FilteredList;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.fxml.Initializable;
import javafx.geometry.Insets;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.layout.Region;
import javafx.scene.layout.TilePane;
import javafx.stage.Modality;
import javafx.stage.Stage;

import java.net.URL;
import java.util.ResourceBundle;

public class BudgetListController implements Initializable {

    // KPI (match budget.fxml)
    @FXML private Label kpiCountLabel;
    @FXML private Label kpiInitialLabel;
    @FXML private Label kpiRentLabel;
    @FXML private Label kpiDeficitLabel;

    // Filters (match budget.fxml)
    @FXML private TextField searchField;
    @FXML private ComboBox<Integer> eventFilter;
    @FXML private ComboBox<String> statusFilter;
    @FXML private Button addBtn;

    @FXML private Label statusLabel;

    // Cards/Grid (match budget.fxml)
    @FXML private ScrollPane cardsScroll;
    @FXML private TilePane cardsPane;

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

        if (searchField != null) searchField.textProperty().addListener((obs, o, n) -> applyPredicate());
        if (eventFilter != null) eventFilter.valueProperty().addListener((obs, o, n) -> applyPredicate());
        if (statusFilter != null) statusFilter.valueProperty().addListener((obs, o, n) -> applyPredicate());

        if (addBtn != null) addBtn.setOnAction(e -> onAdd());

        if (cardsScroll != null && cardsPane != null) {
            cardsPane.setPadding(new Insets(8));
            cardsPane.setHgap(14);
            cardsPane.setVgap(14);

            cardsScroll.viewportBoundsProperty().addListener((obs, oldB, b) ->
                    cardsPane.setPrefColumns(computeCols(b.getWidth()))
            );
        }

        setupFilters();
        loadData();
        applyPredicate();
    }

    private int computeCols(double width) {
        if (width < 520) return 1;
        if (width < 980) return 2;
        return 3;
    }

    private void setupFilters() {
        if (eventFilter != null) {
            eventFilter.getItems().setAll(budgetService.getEventIdsFromBudgets());
            eventFilter.setValue(null);
        }
        if (statusFilter != null) {
            statusFilter.getItems().setAll("Tous", "OK", "Déficit");
            statusFilter.setValue("Tous");
        }
    }

    private void loadData() {
        baseList.setAll(budgetService.getAllBudgets());
        updateKpis();

        if (statusLabel != null) {
            statusLabel.setText("📊 " + baseList.size() + " budget(s) • Mise à jour: Maintenant");
        }
        renderCards();
    }

    private void updateKpis() {
        if (kpiCountLabel != null) kpiCountLabel.setText(String.valueOf(budgetService.countBudgets()));
        if (kpiInitialLabel != null) kpiInitialLabel.setText(String.format("%,.2f DT", budgetService.sumInitial()));
        if (kpiRentLabel != null) kpiRentLabel.setText(String.format("%,.2f DT", budgetService.globalRentability()));
        if (kpiDeficitLabel != null) kpiDeficitLabel.setText(String.valueOf(budgetService.countDeficitBudgets()));
    }

    private void applyPredicate() {
        if (filtered == null) return;

        String q = (searchField == null || searchField.getText() == null)
                ? "" : searchField.getText().trim().toLowerCase();

        Integer ev = (eventFilter == null) ? null : eventFilter.getValue();
        String st = (statusFilter == null || statusFilter.getValue() == null) ? "Tous" : statusFilter.getValue();

        filtered.setPredicate(b -> {
            boolean okQ = q.isEmpty()
                    || String.valueOf(b.getId()).contains(q)
                    || String.valueOf(b.getEvent_id()).contains(q);

            boolean okEv = (ev == null) || b.getEvent_id() == ev;

            boolean okStatus;
            if ("OK".equalsIgnoreCase(st)) okStatus = b.getRentabilite() >= 0;
            else if ("Déficit".equalsIgnoreCase(st)) okStatus = b.getRentabilite() < 0;
            else okStatus = true;

            return okQ && okEv && okStatus;
        });

        if (statusLabel != null) {
            statusLabel.setText("📊 " + filtered.size() + " budget(s) filtrés • Mise à jour: Maintenant");
        }
        renderCards();
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
                        () -> openDetails(b),
                        () -> onEdit(b),
                        () -> onDelete(b)
                );

                cardsPane.getChildren().add(cardRoot);
            } catch (Exception e) {
                showError("UI", "Erreur card budget: " + e.getMessage());
            }
        }
    }

    private void onAdd() {
        Budget b = openForm(null);
        if (b == null) return;

        try {
            budgetService.addBudget(b);
            setupFilters();
            loadData();
            openDetails(b);
        } catch (Exception ex) {
            showError("Erreur ajout", ex.getMessage());
        }
    }

    private void onEdit(Budget existing) {
        Budget b = openForm(existing);
        if (b == null) return;

        try {
            budgetService.updateBudget(b);
            setupFilters();
            loadData();
            openDetails(b);
        } catch (Exception ex) {
            showError("Erreur modification", ex.getMessage());
        }
    }

    private void onDelete(Budget b) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Suppression");
        confirm.setHeaderText("Supprimer budget");
        confirm.setContentText("Supprimer budget ID=" + b.getId() + " ?");

        confirm.showAndWait().ifPresent(btn -> {
            if (btn == ButtonType.OK) {
                try {
                    budgetService.deleteBudget(b.getId());
                    setupFilters();
                    loadData();
                } catch (Exception ex) {
                    showError("Erreur suppression", ex.getMessage());
                }
            }
        });
    }

    private Budget openForm(Budget existing) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource(FORM_FXML));
            Parent root = loader.load();

            BudgetFormController ctrl = loader.getController();
            if (existing == null) ctrl.setModeAdd();
            else ctrl.setModeEdit(existing);

            Stage st = new Stage();
            st.initModality(Modality.APPLICATION_MODAL);
            st.setTitle(existing == null ? "Nouveau Budget" : "Modifier Budget");
            st.setScene(new Scene(root));
            st.setResizable(false);
            st.sizeToScene();
            st.showAndWait();

            return ctrl.getResult();
        } catch (Exception e) {
            showError("UI", "Impossible d'ouvrir le formulaire: " + e.getMessage());
            return null;
        }
    }

    private void openDetails(Budget b) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource(DETAILS_FXML));
            Parent root = loader.load();

            BudgetDetailsController ctrl = loader.getController();
            ctrl.setBudget(b);

            Stage st = new Stage();
            st.initModality(Modality.APPLICATION_MODAL);
            st.setTitle("Détails Budget");
            st.setScene(new Scene(root));
            st.setResizable(false);
            st.sizeToScene();
            st.showAndWait();
        } catch (Exception e) {
            showError("Détails", "FXML détails: " + e.getMessage());
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
