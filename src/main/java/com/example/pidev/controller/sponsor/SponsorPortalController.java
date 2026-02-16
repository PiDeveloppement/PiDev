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

public class SponsorPortalController implements Initializable {

    @FXML private ScrollPane rootScroll;

    @FXML private ComboBox<String> emailAccount;

    @FXML private Label mySponsorsLabel;
    @FXML private Label myContributionLabel;
    @FXML private Label myEventsLabel;

    @FXML private TextField searchField;
    @FXML private ComboBox<String> companyFilter;
    @FXML private ComboBox<Integer> eventFilter;

    @FXML private Button addSponsorBtn;
    @FXML private Label statusLabel;

    // ✅ GRID (cards)
    @FXML private ScrollPane cardsScroll;
    @FXML private TilePane cardsPane;

    private final SponsorService sponsorService = new SponsorService();
    private final UploadContractService uploadContractService = new UploadContractService();

    private final ObservableList<Sponsor> baseList = FXCollections.observableArrayList();
    private FilteredList<Sponsor> filtered;

    private String currentEmail = null;

    private static final String CARD_FXML    = "/com/example/pidev/fxml/Sponsor/sponsor-card.fxml";
    private static final String FORM_FXML    = "/com/example/pidev/fxml/Sponsor/sponsor-form.fxml";
    private static final String DETAILS_FXML = "/com/example/pidev/fxml/Sponsor/sponsor-detail.fxml";

    @Override
    public void initialize(URL location, ResourceBundle resources) {

        // 1) charger emails
        if (emailAccount != null) {
            emailAccount.getItems().setAll(sponsorService.getSponsorEmails());
            emailAccount.valueProperty().addListener((obs, oldV, email) -> onAccountSelected(email));
        }

        // 2) filtered list
        filtered = new FilteredList<>(baseList, s -> true);
        filtered.addListener((ListChangeListener<Sponsor>) c -> renderCards());

        // 3) listeners filtres
        if (searchField != null) searchField.textProperty().addListener((obs, o, n) -> applyPredicate());
        if (companyFilter != null) companyFilter.valueProperty().addListener((obs, o, n) -> applyPredicate());
        if (eventFilter != null) eventFilter.valueProperty().addListener((obs, o, n) -> applyPredicate());

        // 4) bouton add
        if (addSponsorBtn != null) addSponsorBtn.setOnAction(e -> onAdd());

        // 5) responsive columns (TilePane)
        if (cardsScroll != null && cardsPane != null) {
            cardsPane.setPadding(new Insets(8));
            cardsPane.setHgap(14);
            cardsPane.setVgap(14);

            cardsScroll.viewportBoundsProperty().addListener((obs, oldB, b) -> {
                cardsPane.setPrefColumns(computeCols(b.getWidth()));
            });
        }

        // état initial
        setPortalEnabled(false);
        clearPortal();
        renderCards();
    }

    private int computeCols(double width) {
        if (width < 520) return 1;
        if (width < 980) return 2;
        return 3;
    }

    private void onAccountSelected(String email) {
        currentEmail = email;

        if (currentEmail == null || currentEmail.isBlank()) {
            setPortalEnabled(false);
            clearPortal();
            return;
        }

        setPortalEnabled(true);
        reloadMine();
    }

    private void setPortalEnabled(boolean enabled) {
        if (addSponsorBtn != null) addSponsorBtn.setDisable(!enabled);
        if (searchField != null) searchField.setDisable(!enabled);
        if (companyFilter != null) companyFilter.setDisable(!enabled);
        if (eventFilter != null) eventFilter.setDisable(!enabled);
        if (cardsScroll != null) cardsScroll.setDisable(!enabled);
    }

    private void clearPortal() {
        baseList.clear();

        if (mySponsorsLabel != null) mySponsorsLabel.setText("0");
        if (myContributionLabel != null) myContributionLabel.setText("0,00 DT");
        if (myEventsLabel != null) myEventsLabel.setText("0");

        if (statusLabel != null) statusLabel.setText("Sélectionnez un compte pour afficher vos sponsors.");
        if (companyFilter != null) companyFilter.getItems().clear();
        if (eventFilter != null) eventFilter.getItems().clear();
    }

    private void reloadMine() {
        baseList.setAll(sponsorService.getSponsorsByEmail(currentEmail));

        updateMyKpis();
        refreshFilterCombos();
        applyPredicate();

        if (statusLabel != null) statusLabel.setText("📊 " + filtered.size() + " sponsor(s)");
    }

    private void updateMyKpis() {
        int count = sponsorService.getMySponsorsCount(currentEmail);
        double sum = sponsorService.getMyTotalContribution(currentEmail);
        int evCount = sponsorService.getMySponsoredEventsCount(currentEmail);

        if (mySponsorsLabel != null) mySponsorsLabel.setText(String.valueOf(count));
        if (myContributionLabel != null) myContributionLabel.setText(String.format("%,.2f DT", sum));
        if (myEventsLabel != null) myEventsLabel.setText(String.valueOf(evCount));
    }

    private void refreshFilterCombos() {
        if (companyFilter != null) {
            companyFilter.getItems().setAll(sponsorService.getCompanyNamesByEmail(currentEmail));
            companyFilter.setValue(null);
        }
        if (eventFilter != null) {
            eventFilter.getItems().setAll(sponsorService.getEventIdsByEmail(currentEmail));
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
                    || (s.getCompany_name() != null && s.getCompany_name().toLowerCase().contains(q));

            boolean okComp = (comp == null)
                    || (s.getCompany_name() != null && s.getCompany_name().equalsIgnoreCase(comp));

            boolean okEv = (ev == null) || s.getEvent_id() == ev;

            return okQ && okComp && okEv;
        });

        if (statusLabel != null) statusLabel.setText("📊 " + filtered.size() + " sponsor(s)");
        renderCards();
    }

    private void renderCards() {
        if (cardsPane == null || filtered == null) return;

        cardsPane.getChildren().clear();

        for (Sponsor s : filtered) {
            try {
                FXMLLoader loader = new FXMLLoader(getClass().getResource(CARD_FXML));
                Parent cardRoot = loader.load();

                if (cardRoot instanceof Region r) {
                    r.setPrefWidth(440);
                    r.setMaxWidth(Double.MAX_VALUE);
                }

                SponsorCardController cellCtrl = loader.getController();

                Runnable pdfAction = () -> onContract(s);

                cellCtrl.setData(
                        s,
                        () -> openDetails(s),
                        pdfAction,
                        () -> onEdit(s),
                        () -> onDelete(s)
                );

                cardsPane.getChildren().add(cardRoot);

            } catch (Exception ex) {
                // ignore
            }
        }
    }

    private void onAdd() {
        if (currentEmail == null || currentEmail.isBlank()) {
            showError("Accès", "Choisissez un compte sponsor d'abord.");
            return;
        }

        Sponsor s = openForm(null, currentEmail);
        if (s == null) return;

        s.setContact_email(currentEmail);

        try {
            sponsorService.addSponsor(s);
            reloadMine();
            openDetails(s);
        } catch (Exception ex) {
            showError("Erreur ajout", ex.getMessage());
        }
    }

    private void onEdit(Sponsor existing) {
        Sponsor updated = openForm(existing, currentEmail);
        if (updated == null) return;

        updated.setContact_email(currentEmail);

        try {
            sponsorService.updateSponsor(updated);
            reloadMine();
            openDetails(updated);
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
                    reloadMine();
                } catch (Exception ex) {
                    showError("Erreur suppression", ex.getMessage());
                }
            }
        });
    }

    private void onContract(Sponsor s) {
        try {
            if (s.getContract_url() == null || s.getContract_url().isBlank()) {
                String url = uploadContractService.generateAndUploadContract(s);
                s.setContract_url(url);
                sponsorService.updateContractUrl(s.getId(), url);
                reloadMine();
                openInBrowser(url);
            } else {
                openInBrowser(s.getContract_url());
            }
        } catch (Exception ex) {
            showError("Contrat PDF", ex.getMessage());
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
            st.setResizable(false);
            st.sizeToScene();
            st.showAndWait();

            return ctrl.getResult();
        } catch (Exception e) {
            showError("UI", "Impossible d'ouvrir le formulaire: " + e.getMessage());
            return null;
        }
    }

    private void openDetails(Sponsor sponsor) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource(DETAILS_FXML));
            Parent root = loader.load();

            SponsorDetailsController ctrl = loader.getController();
            ctrl.setSponsor(sponsor);

            Stage st = new Stage();
            st.initModality(Modality.APPLICATION_MODAL);
            st.setTitle("Détails Sponsor");
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
