package com.example.pidev.controller.sponsor;

import com.example.pidev.model.sponsor.Sponsor;
import javafx.fxml.FXML;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.stage.Stage;

import java.awt.Desktop;
import java.io.InputStream;
import java.net.URI;

public class SponsorDetailsController {

    @FXML private ImageView appLogoView;
    @FXML private ImageView sponsorLogoView;

    @FXML private Label idLabel;
    @FXML private Label eventIdLabel;
    @FXML private Label companyLabel;
    @FXML private Label emailLabel;
    @FXML private Label contributionLabel;
    @FXML private Label logoUrlLabel;
    @FXML private Label contractUrlLabel;

    @FXML private Button openContractBtn;
    @FXML private Button okBtn;

    private static final String APP_LOGO = "/com/example/pidev/icons/logo.png";
    private Sponsor sponsor;

    @FXML
    private void initialize() {
        try (InputStream is = getClass().getResourceAsStream(APP_LOGO)) {
            if (is != null && appLogoView != null) {
                appLogoView.setImage(new Image(is));
            }
        } catch (Exception ignored) {}
    }

    public void setSponsor(Sponsor s) {
        this.sponsor = s;
        if (s == null) return;

        idLabel.setText(String.valueOf(s.getId()));
        eventIdLabel.setText(String.valueOf(s.getEvent_id()));
        companyLabel.setText(nv(s.getCompany_name()));
        emailLabel.setText(nv(s.getContact_email()));
        contributionLabel.setText(String.format("%,.2f DT", s.getContribution_name()));

        if (logoUrlLabel != null) logoUrlLabel.setText(nv(s.getLogo_url()));
        if (contractUrlLabel != null) contractUrlLabel.setText(nv(s.getContract_url()));

        // logo sponsor (URL Cloudinary)
        try {
            if (sponsorLogoView != null && s.getLogo_url() != null && !s.getLogo_url().isBlank()) {
                sponsorLogoView.setImage(new Image(s.getLogo_url(), true));
            } else if (sponsorLogoView != null) {
                sponsorLogoView.setImage(null);
            }
        } catch (Exception ignored) {}

        boolean hasContract = s.getContract_url() != null && !s.getContract_url().isBlank();
        openContractBtn.setDisable(!hasContract);
        openContractBtn.setOpacity(hasContract ? 1.0 : 0.45);
    }

    @FXML
    private void onOpenContract() {
        try {
            if (sponsor == null) return;
            String url = sponsor.getContract_url();
            if (url == null || url.isBlank()) return;
            if (!Desktop.isDesktopSupported()) return;
            Desktop.getDesktop().browse(URI.create(url));
        } catch (Exception ignored) {}
    }

    @FXML
    private void onOk() {
        Stage st = (Stage) okBtn.getScene().getWindow();
        st.close();
    }

    private static String nv(String s) {
        return (s == null || s.isBlank()) ? "—" : s;
    }
}
