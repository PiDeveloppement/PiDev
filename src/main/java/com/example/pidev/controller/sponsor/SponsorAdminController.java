package com.example.pidev.controller.sponsor;

import com.example.pidev.MainController;
import com.example.pidev.model.sponsor.Sponsor;
import com.example.pidev.service.sponsor.SponsorService;
import com.example.pidev.service.pdf.LocalSponsorPdfService;
import com.example.pidev.service.search.LuceneSearchService;
import com.example.pidev.service.excel.ExcelExportService; // AJOUT
import com.example.pidev.service.chart.QuickChartService; // AJOUT
import com.google.gson.JsonObject; // AJOUT
import javafx.collections.FXCollections;
import javafx.collections.ListChangeListener;
import javafx.collections.ObservableList;
import javafx.collections.transformation.FilteredList;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.fxml.Initializable;
import javafx.geometry.Insets;
import javafx.scene.Parent;
import javafx.scene.chart.PieChart;
import javafx.scene.control.*;
import javafx.scene.layout.Region;
import javafx.scene.layout.TilePane;
import javafx.stage.FileChooser; // AJOUT
import javafx.stage.Stage; // AJOUT

import java.awt.Desktop;
import java.io.File;
import java.net.URL;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;
import java.util.ResourceBundle;

public class SponsorAdminController implements Initializable {

    @FXML private Label statusLabel;
    @FXML private Label totalSponsorsLabel;
    @FXML private Label totalContributionLabel;
    @FXML private Label avgContributionLabel;

    @FXML private TextField searchField;
    @FXML private ComboBox<String> companyFilter;
    @FXML private ComboBox<String> eventFilter;
    @FXML private Button addSponsorBtn;
    @FXML private Button exportExcelBtn; // AJOUT

    @FXML private ScrollPane cardsScroll;
    @FXML private TilePane cardsPane;
    @FXML private PieChart contributionsChart;

    private final SponsorService sponsorService = new SponsorService();
    private final LocalSponsorPdfService pdfService = new LocalSponsorPdfService();

    private final ObservableList<Sponsor> baseList = FXCollections.observableArrayList();
    private FilteredList<Sponsor> filtered;

    private static final String CARD_FXML    = "/com/example/pidev/fxml/Sponsor/sponsor-card.fxml";
    private static final String FORM_FXML    = "/com/example/pidev/fxml/Sponsor/sponsor-form.fxml";
    private static final String DETAILS_FXML = "/com/example/pidev/fxml/Sponsor/sponsor-detail.fxml";

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        filtered = new FilteredList<>(baseList, s -> true);
        filtered.addListener((ListChangeListener<Sponsor>) c -> renderCards());

        if (searchField != null) {
            searchField.textProperty().addListener((obs, o, n) -> {
                if (n == null || n.trim().isEmpty()) {
                    loadData();
                } else {
                    searchSponsors(n.trim());
                }
            });
        }

        if (companyFilter != null) companyFilter.valueProperty().addListener((obs, o, n) -> applyPredicate());
        if (eventFilter != null) eventFilter.valueProperty().addListener((obs, o, n) -> applyPredicate());
        if (addSponsorBtn != null) addSponsorBtn.setOnAction(e -> onAdd());
        // AJOUT : listener pour le bouton d'export
        if (exportExcelBtn != null) exportExcelBtn.setOnAction(e -> exportSponsorsToExcel());

        if (cardsScroll != null && cardsPane != null) {
            cardsPane.setPadding(new Insets(8));
            cardsPane.setHgap(14);
            cardsPane.setVgap(14);
            cardsScroll.viewportBoundsProperty().addListener((obs, oldB, b) ->
                    cardsPane.setPrefColumns(computeCols(b.getWidth())));
        }

        setupFiltersCombos();
        loadData();
        applyPredicate();
        initCharts();

        // Réindexation au démarrage
        reindexAllSponsors();
    }

    private void searchSponsors(String query) {
        try {
            List<Integer> ids = LuceneSearchService.searchSponsors(query);
            List<Sponsor> found = new ArrayList<>();
            for (Integer id : ids) {
                Sponsor s = sponsorService.getSponsorById(id);
                if (s != null) found.add(s);
            }
            baseList.setAll(found);
            updateGlobalStats();
            if (statusLabel != null) {
                statusLabel.setText("🔍 " + found.size() + " résultat(s) pour \"" + query + "\"");
            }
            renderCards();
        } catch (Exception e) {
            e.printStackTrace();
            showError("Recherche", "Erreur lors de la recherche : " + e.getMessage());
        }
    }

    private void reindexAllSponsors() {
        try {
            List<Sponsor> all = sponsorService.getAllSponsors();
            for (Sponsor s : all) {
                LuceneSearchService.indexSponsor(s.getId(), s.getCompany_name(), s.getContact_email(), s.getContribution_name(), s.getEvent_id());
            }
            System.out.println("Indexation terminée : " + all.size() + " sponsors");
        } catch (Exception e) {
            e.printStackTrace();
        }
    }

    private int computeCols(double width) {
        if (width < 520) return 1;
        if (width < 980) return 2;
        return 3;
    }

    private void loadData() {
        try {
            baseList.setAll(sponsorService.getAllSponsors());
            updateGlobalStats();
            if (statusLabel != null)
                statusLabel.setText("📊 " + baseList.size() + " sponsors • Mise à jour: Maintenant");
            renderCards();
        } catch (Exception e) {
            showError("DB", e.getMessage());
        }
    }

    private void setupFiltersCombos() {
        try {
            if (companyFilter != null) {
                companyFilter.getItems().setAll(sponsorService.getCompanyNamesAll());
                companyFilter.setValue(null);
            }
            if (eventFilter != null) {
                ObservableList<String> titles = sponsorService.getAllEventTitles();
                eventFilter.getItems().setAll(titles);
                eventFilter.setValue(null);
            }
        } catch (Exception e) {
            showError("Filtres", e.getMessage());
        }
    }

    private void applyPredicate() {
        if (filtered == null) return;

        String q = (searchField == null || searchField.getText() == null) ? "" : searchField.getText().trim().toLowerCase();
        String comp = (companyFilter == null) ? null : companyFilter.getValue();
        String eventTitle = (eventFilter == null) ? null : eventFilter.getValue();

        Integer eventId = null;
        if (eventTitle != null && !eventTitle.isBlank()) {
            try {
                eventId = sponsorService.getEventIdByTitle(eventTitle);
            } catch (Exception ignored) {}
        }
        Integer finalEventId = eventId;

        filtered.setPredicate(s -> {
            boolean okQ = q.isEmpty()
                    || String.valueOf(s.getId()).contains(q)
                    || (s.getCompany_name() != null && s.getCompany_name().toLowerCase().contains(q))
                    || (s.getContact_email() != null && s.getContact_email().toLowerCase().contains(q));

            boolean okComp = (comp == null) || (s.getCompany_name() != null && s.getCompany_name().equalsIgnoreCase(comp));
            boolean okEv = (finalEventId == null) || s.getEvent_id() == finalEventId;

            return okQ && okComp && okEv;
        });

        if (statusLabel != null)
            statusLabel.setText("📊 " + filtered.size() + " sponsors filtrés • Mise à jour: Maintenant");
        renderCards();
    }

    private void renderCards() {
        if (cardsPane == null || filtered == null) return;
        cardsPane.getChildren().clear();

        for (Sponsor sponsor : filtered) {
            try {
                FXMLLoader loader = new FXMLLoader(getClass().getResource(CARD_FXML));
                Parent root = loader.load();
                if (root instanceof Region r) {
                    r.setPrefWidth(440);
                    r.setMaxWidth(Double.MAX_VALUE);
                }

                SponsorCardController card = loader.getController();
                card.setData(
                        sponsor,
                        () -> openDetailsAsPage(sponsor),
                        () -> onGeneratePdfFromDetails(sponsor),
                        () -> onEdit(sponsor),
                        () -> onDelete(sponsor)
                );

                cardsPane.getChildren().add(root);
            } catch (Exception ex) {
                showError("UI", "Erreur card sponsor: " + ex.getMessage());
            }
        }
    }

    private void updateGlobalStats() {
        try {
            if (totalSponsorsLabel != null)
                totalSponsorsLabel.setText(String.valueOf(sponsorService.getTotalSponsors()));
            if (totalContributionLabel != null)
                totalContributionLabel.setText(String.format("%,.2f DT", sponsorService.getTotalContribution()));
            if (avgContributionLabel != null)
                avgContributionLabel.setText(String.format("%,.2f DT", sponsorService.getAverageContribution()));
        } catch (Exception e) {
            showError("Stats", e.getMessage());
        }
    }

    private void initCharts() {
        try {
            Map<String, Double> data = sponsorService.getTotalContributionByEvent();
            ObservableList<PieChart.Data> pieData = FXCollections.observableArrayList();
            for (Map.Entry<String, Double> entry : data.entrySet()) {
                if (entry.getValue() > 0) {
                    pieData.add(new PieChart.Data(entry.getKey(), entry.getValue()));
                }
            }
            contributionsChart.setData(pieData);
            contributionsChart.setTitle("Répartition des contributions par événement");
            contributionsChart.setLabelsVisible(true);
            contributionsChart.setLegendVisible(true);
        } catch (Exception e) {
            showError("Chart", "Impossible de charger le graphique : " + e.getMessage());
        }
    }

    private void onAdd() {
        openFormAsPage(null, null);
    }

    private void onEdit(Sponsor existing) {
        openFormAsPage(existing, null);
    }

    private void openFormAsPage(Sponsor existing, String fixedEmail) {
        MainController.getInstance().loadIntoCenter(
                FORM_FXML,
                (SponsorFormController ctrl) -> {
                    ctrl.setFixedEmail(fixedEmail);
                    if (existing == null) ctrl.setModeAdd();
                    else ctrl.setModeEdit(existing);
                    ctrl.setOnSaved(saved -> {
                        setupFiltersCombos();
                        loadData();
                        openDetailsAsPage(saved);
                    });
                    ctrl.setOnFormDone(() -> {
                        MainController.getInstance().showSponsorsAdmin();
                    });
                }
        );
    }

    private void onDelete(Sponsor s) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Suppression");
        confirm.setHeaderText("Supprimer sponsor");
        confirm.setContentText("Supprimer: " + s.getCompany_name() + " ?");

        confirm.showAndWait().ifPresent(btn -> {
            if (btn == ButtonType.OK) {
                try {
                    sponsorService.deleteSponsor(s.getId());
                    setupFiltersCombos();
                    loadData();
                } catch (Exception ex) {
                    showError("Erreur suppression", ex.getMessage());
                }
            }
        });
    }

    private void onGeneratePdfFromDetails(Sponsor s) {
        try {
            if (s == null) return;
            String eventTitle = sponsorService.getEventTitleById(s.getEvent_id());
            File pdf = pdfService.generateSponsorContractPdf(s, eventTitle);
            if (!Desktop.isDesktopSupported()) {
                showError("PDF", "Desktop non supporté sur cette machine.");
                return;
            }
            Desktop.getDesktop().open(pdf);
        } catch (Exception ex) {
            showError("PDF", ex.getMessage());
        }
    }

    private void openDetailsAsPage(Sponsor sponsor) {
        try {
            MainController.getInstance().loadIntoCenter(
                    DETAILS_FXML,
                    (SponsorDetailsController ctrl) -> {
                        ctrl.setSponsor(sponsor);
                        ctrl.setOnBack(() -> {
                            setupFiltersCombos();
                            loadData();
                            MainController.getInstance().showSponsorsAdmin();
                        });
                    }
            );
        } catch (Exception e) {
            showError("UI", "Impossible d'ouvrir détails: " + e.getMessage());
        }
    }

    // AJOUT : Méthode d'export Excel
    private void exportSponsorsToExcel() {
        try {
            List<Sponsor> allSponsors = sponsorService.getAllSponsors();
            Map<String, Double> data = sponsorService.getTotalContributionByEvent();
            JsonObject chartConfig = QuickChartService.createDoughnutChart(
                    "Répartition des contributions",
                    data.keySet().toArray(new String[0]),
                    data.values().stream().mapToDouble(Double::doubleValue).toArray()
            );

            FileChooser fileChooser = new FileChooser();
            fileChooser.setTitle("Enregistrer le fichier Excel");
            fileChooser.getExtensionFilters().add(new FileChooser.ExtensionFilter("Fichiers Excel", "*.xlsx"));
            File file = fileChooser.showSaveDialog(getStage());
            if (file != null) {
                ExcelExportService.exportSponsors(allSponsors, chartConfig, file.getAbsolutePath());
                showInfo("Export réussi", "Le fichier Excel a été généré avec succès.");
            }
        } catch (Exception e) {
            e.printStackTrace();
            showError("Export", "Erreur lors de l'export : " + e.getMessage());
        }
    }

    // AJOUT : Méthode utilitaire pour obtenir la fenêtre
    private Stage getStage() {
        return (Stage) addSponsorBtn.getScene().getWindow();
    }

    // AJOUT : Méthode pour afficher une info
    private void showInfo(String title, String msg) {
        Alert a = new Alert(Alert.AlertType.INFORMATION);
        a.setTitle(title);
        a.setHeaderText(null);
        a.setContentText(msg);
        a.showAndWait();
    }

    private void showError(String title, String msg) {
        Alert a = new Alert(Alert.AlertType.ERROR);
        a.setTitle(title);
        a.setHeaderText(null);
        a.setContentText(msg);
        a.showAndWait();
    }
}