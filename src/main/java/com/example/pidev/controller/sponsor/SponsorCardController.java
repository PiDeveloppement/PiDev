package com.example.pidev.controller.sponsor;

import com.example.pidev.model.sponsor.Sponsor;
import javafx.fxml.FXML;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;

import java.util.Objects;

public class SponsorCardController {

    @FXML private ImageView logoView;

    @FXML private Label companyLabel;
    @FXML private Label idLabel;
    @FXML private Label eventLabel;
    @FXML private Label emailLabel;
    @FXML private Label contributionLabel;

    @FXML private Button detailsBtn;
    @FXML private Button pdfBtn;
    @FXML private Button editBtn;
    @FXML private Button deleteBtn;

    public void setData(
            Sponsor s,
            Runnable onDetails,
            Runnable onPdf,
            Runnable onEdit,
            Runnable onDelete
    ) {
        if (s == null) return;

        if (companyLabel != null) companyLabel.setText(nvl(s.getCompany_name()));
        if (emailLabel != null) emailLabel.setText(nvl(s.getContact_email()));
        if (idLabel != null) idLabel.setText("ID: " + s.getId());
        if (eventLabel != null) eventLabel.setText("Event: " + s.getEvent_id());

        // contribution_name semble être decimal dans ta table
        if (contributionLabel != null) {
            contributionLabel.setText(String.format("%,.2f DT", s.getContribution_name()));
        }

        // logo (si tu as un url)
        if (logoView != null) {
            try {
                String url = s.getLogo_url();
                if (url != null && !url.isBlank()) {
                    logoView.setImage(new Image(url, true));
                } else {
                    logoView.setImage(null);
                }
            } catch (Exception ignored) {
                logoView.setImage(null);
            }
        }

        if (detailsBtn != null) detailsBtn.setOnAction(e -> { if (onDetails != null) onDetails.run(); });
        if (pdfBtn != null) pdfBtn.setOnAction(e -> { if (onPdf != null) onPdf.run(); });
        if (editBtn != null) editBtn.setOnAction(e -> { if (onEdit != null) onEdit.run(); });
        if (deleteBtn != null) deleteBtn.setOnAction(e -> { if (onDelete != null) onDelete.run(); });
    }

    private String nvl(String s) {
        return (s == null) ? "" : s;
    }
}
