package com.example.pidev.controller.depense;

import com.example.pidev.model.depense.Depense;
import com.example.pidev.service.depense.DepenseService;
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
import java.time.LocalDate;
import java.time.YearMonth;
import java.util.ResourceBundle;

public class DepenseListController implements Initializable {

    // KPI (fx:id du FXML)
    @FXML private Label kpiNombreDepensesLabel;
    @FXML private Label kpiTotalDepensesLabel;
    @FXML private Label kpiDepensesMoisLabel;
    @FXML private Label kpiCategorieTopLabel;

    // Filters (fx:id du FXML)
    @FXML private TextField champRecherche;
    @FXML private ComboBox<Integer> filtreBudget;
    @FXML private ComboBox<String> filtreCategorie;
    @FXML private ComboBox<String> filtrePeriode;
    @FXML private Button boutonNouvelleDepense;

    @FXML private Label labelStatut;

    // Cards container (à ajouter dans depense.fxml)
    @FXML private ScrollPane cardsScroll;
    @FXML private TilePane cardsPane;

    private final DepenseService depenseService = new DepenseService();

    private final ObservableList<Depense> baseList = FXCollections.observableArrayList();
    private FilteredList<Depense> filtered;

    private static final String CARD_FXML    = "/com/example/pidev/fxml/Depense/depense-card.fxml";
    private static final String FORM_FXML    = "/com/example/pidev/fxml/Depense/depense-form.fxml";
    private static final String DETAILS_FXML = "/com/example/pidev/fxml/Depense/depense-detail.fxml";

    @Override
    public void initialize(URL location, ResourceBundle resources) {

        filtered = new FilteredList<>(baseList, d -> true);
        filtered.addListener((ListChangeListener<Depense>) c -> renderCards());

        if (champRecherche != null) champRecherche.textProperty().addListener((obs, o, n) -> applyPredicate());
        if (filtreBudget != null) filtreBudget.valueProperty().addListener((obs, o, n) -> applyPredicate());
        if (filtreCategorie != null) filtreCategorie.valueProperty().addListener((obs, o, n) -> applyPredicate());
        if (filtrePeriode != null) filtrePeriode.valueProperty().addListener((obs, o, n) -> applyPredicate());

        if (boutonNouvelleDepense != null) boutonNouvelleDepense.setOnAction(e -> onAdd());

        if (cardsScroll != null && cardsPane != null) {
            cardsPane.setPadding(new Insets(8));
            cardsPane.setHgap(14);
            cardsPane.setVgap(14);
            cardsScroll.viewportBoundsProperty().addListener((obs, oldB, b) ->
                    cardsPane.setPrefColumns(computeCols(b.getWidth()))
            );
        }

        setupPeriode();
        loadData();
        applyPredicate();
    }

    private void setupPeriode() {
        if (filtrePeriode == null) return;
        filtrePeriode.getItems().setAll("Toutes", "Ce mois", "Ce trimestre", "Cette année");
        filtrePeriode.setValue("Toutes");
    }

    private int computeCols(double width) {
        if (width < 520) return 1;
        if (width < 980) return 2;
        return 3;
    }

    private void loadData() {
        baseList.setAll(depenseService.getAllDepenses());

        if (filtreBudget != null) {
            filtreBudget.getItems().setAll(depenseService.getBudgetIdsFromDepenses());
            filtreBudget.setValue(null);
        }
        if (filtreCategorie != null) {
            filtreCategorie.getItems().setAll(depenseService.getCategories());
            filtreCategorie.setValue(null);
        }

        updateKpis();

        if (labelStatut != null) {
            labelStatut.setText("📊 " + baseList.size() + " dépense(s) • Mise à jour: Maintenant");
        }

        renderCards();
    }

    private void updateKpis() {
        int count = depenseService.countDepenses();
        double total = depenseService.sumDepenses();

        YearMonth now = YearMonth.now();
        LocalDate from = now.atDay(1);
        LocalDate to = now.atEndOfMonth();

        double monthTotal = depenseService.sumDepensesBetween(from, to);
        String topCat = depenseService.topCategorie();

        if (kpiNombreDepensesLabel != null) kpiNombreDepensesLabel.setText(String.valueOf(count));
        if (kpiTotalDepensesLabel != null) kpiTotalDepensesLabel.setText(String.format("%,.2f DT", total));
        if (kpiDepensesMoisLabel != null) kpiDepensesMoisLabel.setText(String.format("%,.2f DT", monthTotal));
        if (kpiCategorieTopLabel != null) kpiCategorieTopLabel.setText(topCat == null ? "—" : topCat);
    }

    private void applyPredicate() {
        if (filtered == null) return;

        String q = (champRecherche == null || champRecherche.getText() == null)
                ? "" : champRecherche.getText().trim().toLowerCase();

        Integer bud = (filtreBudget == null) ? null : filtreBudget.getValue();
        String cat = (filtreCategorie == null) ? null : filtreCategorie.getValue();
        String periode = (filtrePeriode == null || filtrePeriode.getValue() == null) ? "Toutes" : filtrePeriode.getValue();

        LocalDate minDate = null;
        LocalDate maxDate = null;
        LocalDate today = LocalDate.now();

        switch (periode) {
            case "Ce mois" -> {
                YearMonth ym = YearMonth.now();
                minDate = ym.atDay(1);
                maxDate = ym.atEndOfMonth();
            }
            case "Ce trimestre" -> {
                int qtr = (today.getMonthValue() - 1) / 3;
                int startMonth = qtr * 3 + 1;
                YearMonth start = YearMonth.of(today.getYear(), startMonth);
                YearMonth end = YearMonth.of(today.getYear(), startMonth + 2);
                minDate = start.atDay(1);
                maxDate = end.atEndOfMonth();
            }
            case "Cette année" -> {
                minDate = LocalDate.of(today.getYear(), 1, 1);
                maxDate = LocalDate.of(today.getYear(), 12, 31);
            }
            default -> { /* Toutes */ }
        }

        LocalDate finalMin = minDate;
        LocalDate finalMax = maxDate;

        filtered.setPredicate(d -> {
            boolean okQ = q.isEmpty()
                    || String.valueOf(d.getId()).contains(q)
                    || String.valueOf(d.getBudget_id()).contains(q)
                    || (d.getDescription() != null && d.getDescription().toLowerCase().contains(q))
                    || (d.getCategory() != null && d.getCategory().toLowerCase().contains(q));

            boolean okBudget = (bud == null) || (d.getBudget_id() == bud);
            boolean okCat = (cat == null) || (d.getCategory() != null && d.getCategory().equalsIgnoreCase(cat));

            boolean okPeriode = true;
            if (finalMin != null && finalMax != null) {
                if (d.getExpense_date() == null) okPeriode = false;
                else okPeriode = !d.getExpense_date().isBefore(finalMin) && !d.getExpense_date().isAfter(finalMax);
            }

            return okQ && okBudget && okCat && okPeriode;
        });

        if (labelStatut != null) {
            labelStatut.setText("📊 " + filtered.size() + " dépense(s) filtrées • Mise à jour: Maintenant");
        }

        renderCards();
    }

    private void renderCards() {
        if (cardsPane == null || filtered == null) return;

        cardsPane.getChildren().clear();

        for (Depense d : filtered) {
            try {
                FXMLLoader loader = new FXMLLoader(getClass().getResource(CARD_FXML));
                Parent cardRoot = loader.load();

                if (cardRoot instanceof Region r) {
                    r.setPrefWidth(440);
                    r.setMaxWidth(Double.MAX_VALUE);
                }

                Object ctrl = loader.getController();
                if (!(ctrl instanceof DepenseCardController cell)) {
                    throw new IllegalStateException("depense-card.fxml controller=" + ctrl.getClass().getName()
                            + " (attendu DepenseCardController)");
                }

                cell.setData(d, () -> openDetails(d), () -> onEdit(d), () -> onDelete(d));

                cardsPane.getChildren().add(cardRoot);

            } catch (Exception ex) {
                showError("UI", "Erreur card depense: " + ex.getMessage());
            }
        }
    }

    private void onAdd() {
        Depense created = openForm(null);
        if (created == null) return;

        try {
            depenseService.addDepense(created);
            loadData();
            openDetails(created);
        } catch (Exception ex) {
            showError("Erreur ajout", ex.getMessage());
        }
    }

    private void onEdit(Depense existing) {
        if (existing == null) return;

        int oldBudgetId = existing.getBudget_id();
        Depense updated = openForm(existing);
        if (updated == null) return;

        try {
            depenseService.updateDepense(updated, oldBudgetId);
            loadData();
            openDetails(updated);
        } catch (Exception ex) {
            showError("Erreur modification", ex.getMessage());
        }
    }

    private void onDelete(Depense d) {
        if (d == null) return;

        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Suppression");
        confirm.setHeaderText("Supprimer dépense");
        confirm.setContentText("Supprimer dépense ID=" + d.getId() + " ?");

        confirm.showAndWait().ifPresent(btn -> {
            if (btn == ButtonType.OK) {
                try {
                    depenseService.deleteDepense(d.getId(), d.getBudget_id());
                    loadData();
                } catch (Exception ex) {
                    showError("Erreur suppression", ex.getMessage());
                }
            }
        });
    }

    private Depense openForm(Depense existing) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource(FORM_FXML));
            Parent root = loader.load();

            DepenseFormController ctrl = loader.getController();
            ctrl.setDepense(existing);

            Stage st = new Stage();
            st.initModality(Modality.APPLICATION_MODAL);
            st.setTitle(existing == null ? "Nouvelle Dépense" : "Modifier Dépense");
            st.setScene(new Scene(root));
            st.setResizable(false);
            st.sizeToScene();
            st.showAndWait();

            if (!ctrl.isSaved()) return null;
            return ctrl.getDepense();
        } catch (Exception e) {
            showError("UI", "Impossible d'ouvrir le formulaire: " + e.getMessage());
            return null;
        }
    }

    private void openDetails(Depense d) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource(DETAILS_FXML));
            Parent root = loader.load();

            DepenseDetailsController ctrl = loader.getController();
            ctrl.setDepense(d);

            Stage st = new Stage();
            st.initModality(Modality.APPLICATION_MODAL);
            st.setTitle("Détails Dépense");
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
