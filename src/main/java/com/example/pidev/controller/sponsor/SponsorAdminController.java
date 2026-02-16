package com.example.pidev.controller.sponsor;

import com.example.pidev.model.sponsor.Sponsor;
import com.example.pidev.service.sponsor.SponsorService;
import com.example.pidev.service.upload.UploadContractService;
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

import java.awt.Desktop;
import java.net.URI;
import java.net.URL;
import java.util.ResourceBundle;

public class SponsorAdminController implements Initializable {

    // KPI
    @FXML private Label statusLabel;
    @FXML private Label totalSponsorsLabel;
    @FXML private Label totalContributionLabel;
    @FXML private Label avgContributionLabel;

    // Filters
    @FXML private TextField searchField;
    @FXML private ComboBox<String> companyFilter;
    @FXML private ComboBox<Integer> eventFilter;
    @FXML private Button addSponsorBtn;

    // ✅ GRID
    @FXML private ScrollPane cardsScroll;
    @FXML private TilePane cardsPane;

    private final SponsorService sponsorService = new SponsorService();
    private final UploadContractService uploadContractService = new UploadContractService();

    private final ObservableList<Sponsor> baseList = FXCollections.observableArrayList();
    private FilteredList<Sponsor> filtered;

    // ✅ ON UTILISE LA MÊME CARD QUE PORTAL
    private static final String CARD_FXML    = "/com/example/pidev/fxml/Sponsor/sponsor-card.fxml";
    private static final String FORM_FXML    = "/com/example/pidev/fxml/Sponsor/sponsor-form.fxml";
    private static final String DETAILS_FXML = "/com/example/pidev/fxml/Sponsor/sponsor-detail.fxml";

    @Override
    public void initialize(URL location, ResourceBundle resources) {

        // list + filtered
        filtered = new FilteredList<>(baseList, s -> true);
        filtered.addListener((ListChangeListener<Sponsor>) c -> renderCards());

        // filters listeners
        if (searchField != null) searchField.textProperty().addListener((obs, o, n) -> applyPredicate());
        if (companyFilter != null) companyFilter.valueProperty().addListener((obs, o, n) -> applyPredicate());
        if (eventFilter != null) eventFilter.valueProperty().addListener((obs, o, n) -> applyPredicate());

        // add
        if (addSponsorBtn != null) addSponsorBtn.setOnAction(e -> onAdd());

        // responsive columns
        if (cardsScroll != null && cardsPane != null) {
            cardsPane.setPadding(new Insets(8));
            cardsPane.setHgap(14);
            cardsPane.setVgap(14);

            cardsScroll.viewportBoundsProperty().addListener((obs, oldB, b) -> {
                cardsPane.setPrefColumns(computeCols(b.getWidth()));
            });
        }

        // load
        setupFiltersCombos();
        loadData();
        applyPredicate();
    }

    private int computeCols(double width) {
        if (width < 520) return 1;
        if (width < 980) return 2;
        return 3;
    }

    private void loadData() {
        baseList.setAll(sponsorService.getAllSponsors());
        updateGlobalStats();

        if (statusLabel != null) {
            statusLabel.setText("📊 " + baseList.size() + " sponsors • Mise à jour: Maintenant");
        }
        renderCards();
    }

    private void setupFiltersCombos() {
        if (companyFilter != null) {
            companyFilter.getItems().setAll(sponsorService.getCompanyNamesAll());
            companyFilter.setValue(null);
        }
        if (eventFilter != null) {
            eventFilter.getItems().setAll(sponsorService.getEventIdsAll());
            eventFilter.setValue(null);
        }
    }

    private void applyPredicate() {
        if (filtered == null) return;

        String q = (searchField == null || searchField.getText() == null)
                ? ""
                : searchField.getText().trim().toLowerCase();

        String comp = (companyFilter == null) ? null : companyFilter.getValue();
        Integer ev = (eventFilter == null) ? null : eventFilter.getValue();

        filtered.setPredicate(s -> {
            boolean okQ = q.isEmpty()
                    || String.valueOf(s.getId()).contains(q)
                    || (s.getCompany_name() != null && s.getCompany_name().toLowerCase().contains(q))
                    || (s.getContact_email() != null && s.getContact_email().toLowerCase().contains(q));

            boolean okComp = (comp == null)
                    || (s.getCompany_name() != null && s.getCompany_name().equalsIgnoreCase(comp));

            boolean okEv = (ev == null) || s.getEvent_id() == ev;

            return okQ && okComp && okEv;
        });

        if (statusLabel != null) {
            statusLabel.setText("📊 " + filtered.size() + " sponsors filtrés • Mise à jour: Maintenant");
        }

        renderCards();
    }

    private void renderCards() {
        if (cardsPane == null || filtered == null) return;

        cardsPane.getChildren().clear();

        for (Sponsor s : filtered) {
            try {
                FXMLLoader loader = new FXMLLoader(getClass().getResource(CARD_FXML));
                Parent cardRoot = loader.load();

                // largeur carte
                if (cardRoot instanceof Region r) {
                    r.setPrefWidth(440);
                    r.setMaxWidth(Double.MAX_VALUE);
                }

                // ✅ ICI : SponsorCardController (PAS Admin)
                SponsorCardController cellCtrl = loader.getController();

                cellCtrl.setData(
                        s,
                        () -> openDetails(s),
                        () -> onOpenOrGenerateContract(s),
                        () -> onEdit(s),
                        () -> onDelete(s)
                );

                cardsPane.getChildren().add(cardRoot);

            } catch (Exception ignored) {
                // si une card plante, on continue
            }
        }
    }

    private void updateGlobalStats() {
        if (totalSponsorsLabel != null) {
            totalSponsorsLabel.setText(String.valueOf(sponsorService.getTotalSponsors()));
        }
        if (totalContributionLabel != null) {
            totalContributionLabel.setText(String.format("%,.2f DT", sponsorService.getTotalContribution()));
        }
        if (avgContributionLabel != null) {
            avgContributionLabel.setText(String.format("%,.2f DT", sponsorService.getAverageContribution()));
        }
    }

    private void onAdd() {
        Sponsor s = openForm(null, null);
        if (s == null) return;

        try {
            sponsorService.addSponsor(s);
            setupFiltersCombos();
            loadData();
            openDetails(s);
        } catch (Exception ex) {
            showError("Erreur ajout", ex.getMessage());
        }
    }

    private void onEdit(Sponsor existing) {
        Sponsor s = openForm(existing, null);
        if (s == null) return;

        try {
            sponsorService.updateSponsor(s);
            setupFiltersCombos();
            loadData();
            openDetails(s);
        } catch (Exception ex) {
            showError("Erreur modification", ex.getMessage());
        }
    }

    private void onDelete(Sponsor s) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setTitle("Suppression");
        confirm.setHeaderText("Supprimer sponsor");
        confirm.setContentText("Supprimer: " + s.getCompany_name() + " (id=" + s.getId() + ") ?");

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

    private void onOpenOrGenerateContract(Sponsor s) {
        try {
            if (s == null) return;

            if (s.getContract_url() == null || s.getContract_url().isBlank()) {
                String url = uploadContractService.generateAndUploadContract(s);
                s.setContract_url(url);
                sponsorService.updateContractUrl(s.getId(), url);
                loadData();
                openInBrowser(url);
            } else {
                openInBrowser(s.getContract_url());
            }
        } catch (Exception ex) {
            showError("Contrat", ex.getMessage());
        }
    }

    private void openInBrowser(String url) throws Exception {
        if (url == null || url.isBlank()) return;
        if (!Desktop.isDesktopSupported()) return;
        Desktop.getDesktop().browse(URI.create(url));
    }

    private Sponsor openForm(Sponsor existing, String fixedEmail) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource(FORM_FXML));
            Parent root = loader.load();

            SponsorFormController ctrl = loader.getController();
            ctrl.setFixedEmail(fixedEmail);

            if (existing == null) ctrl.setModeAdd();
            else ctrl.setModeEdit(existing);

            Stage st = new Stage();
            st.initModality(Modality.APPLICATION_MODAL);
            st.setTitle(existing == null ? "Nouveau Sponsor" : "Modifier Sponsor");
            st.setScene(new Scene(root));
            st.setWidth(520);
            st.setHeight(720);
            st.setResizable(true);
            st.showAndWait();

            return ctrl.getResult();
        } catch (Exception e) {
            showError("UI", "Impossible d'ouvrir le formulaire: " + e.getMessage());
            return null;
        }
    }

    private void openDetails(Sponsor sponsor) {
        try {
            URL url = getClass().getResource(DETAILS_FXML);
            if (url == null) throw new IllegalStateException("FXML introuvable: " + DETAILS_FXML);

            FXMLLoader loader = new FXMLLoader(url);
            Parent root = loader.load();

            SponsorDetailsController ctrl = loader.getController();
            ctrl.setSponsor(sponsor);

            Stage st = new Stage();
            st.initModality(Modality.APPLICATION_MODAL);
            st.setTitle("Détails Sponsor");
            st.setScene(new Scene(root));
            st.setWidth(900);
            st.setHeight(540);
            st.setResizable(false);
            st.showAndWait();
        } catch (Exception e) {
            showError("Détails", e.getMessage());
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
