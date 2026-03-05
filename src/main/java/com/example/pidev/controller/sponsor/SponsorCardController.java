package com.example.pidev.controller.sponsor;

import com.example.pidev.model.sponsor.Sponsor;
import com.example.pidev.service.sponsor.SponsorService;
import javafx.fxml.FXML;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;

public class SponsorCardController {

    @FXML private ImageView logoView;
    @FXML private Label companyLabel;
    @FXML private Label emailLabel;
    @FXML private Label contributionLabel;
    @FXML private Label eventLabelPrefix;
    @FXML private Label eventLabel;
    @FXML private Button detailsBtn;
    @FXML private Button pdfBtn;
    @FXML private Button editBtn;
    @FXML private Button deleteBtn;

    private final SponsorService sponsorService = new SponsorService();

    public void setData(Sponsor sponsor, Runnable onDetails, Runnable onPdf, Runnable onEdit, Runnable onDelete) {
        if (sponsor == null) return;

        if (companyLabel != null) companyLabel.setText(nv(sponsor.getCompany_name()));
        if (emailLabel != null) emailLabel.setText(nv(sponsor.getContact_email()));
        if (contributionLabel != null) contributionLabel.setText(String.format("%,.2f DT", sponsor.getContribution_name()));

        if (eventLabel != null) {
            String title = null;
            try {
                title = sponsorService.getEventTitleById(sponsor.getEvent_id());
            } catch (Exception ignored) {
            }
            if (title == null || title.isBlank()) title = "-";
            eventLabel.setText(title);
        }

        if (logoView != null) {
            String logoUrl = sponsor.getLogo_url();
            if (logoUrl != null && !logoUrl.isBlank()) {
                try {
                    Image img = new Image(logoUrl, true);
                    img.errorProperty().addListener((obs, wasError, isError) -> {
                        if (Boolean.TRUE.equals(isError)) {
                            logoView.setImage(null);
                        }
                    });
                    logoView.setImage(img);
                } catch (Exception ignored) {
                    logoView.setImage(null);
                }
            } else {
                logoView.setImage(null);
            }
        }

        if (detailsBtn != null) detailsBtn.setOnAction(e -> { if (onDetails != null) onDetails.run(); });
        if (pdfBtn != null) pdfBtn.setOnAction(e -> { if (onPdf != null) onPdf.run(); });
        if (editBtn != null) editBtn.setOnAction(e -> { if (onEdit != null) onEdit.run(); });
        if (deleteBtn != null) deleteBtn.setOnAction(e -> { if (onDelete != null) onDelete.run(); });
    }

    private static String nv(String value) {
        return (value == null || value.isBlank()) ? "-" : value;
    }
}
