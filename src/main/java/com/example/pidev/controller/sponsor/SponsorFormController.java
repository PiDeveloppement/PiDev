package com.example.pidev.controller.sponsor;

import com.example.pidev.model.sponsor.Sponsor;
import com.example.pidev.service.upload.CloudinaryUploadService;
import javafx.fxml.FXML;
import javafx.scene.control.Label;
import javafx.scene.control.TextField;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.stage.FileChooser;
import javafx.stage.Stage;

import java.io.File;
import java.util.regex.Pattern;

public class SponsorFormController {

    @FXML private Label titleLabel;
    @FXML private TextField eventIdField;
    @FXML private TextField companyField;
    @FXML private TextField emailField;

    @FXML private TextField logoField;
    @FXML private Label logoFileLabel;
    @FXML private ImageView logoPreview;

    @FXML private TextField contributionField;
    @FXML private Label errorLabel;

    private Sponsor editing;
    private Sponsor result;

    private String fixedEmail;
    private File selectedLogoFile;

    private final CloudinaryUploadService cloud = new CloudinaryUploadService();

    private static final Pattern EMAIL_RX =
            Pattern.compile("^[A-Za-z0-9+_.-]+@[A-Za-z0-9.-]+$");

    public void setFixedEmail(String email) {
        this.fixedEmail = (email == null) ? null : email.trim();
        if (this.fixedEmail != null && !this.fixedEmail.isEmpty()) {
            emailField.setText(this.fixedEmail);
            emailField.setDisable(true);
        } else {
            emailField.setDisable(false);
        }
    }

    public void setModeAdd() {
        titleLabel.setText("➕ Nouveau Sponsor");
        editing = null;
        result = null;
        clearErrors();

        eventIdField.clear();
        companyField.clear();
        contributionField.clear();

        selectedLogoFile = null;
        logoField.clear();
        logoPreview.setImage(null);

        if (logoFileLabel != null) logoFileLabel.setText("Aucun fichier choisi");

        if (fixedEmail == null) {
            emailField.clear();
            emailField.setDisable(false);
        } else {
            emailField.setText(fixedEmail);
            emailField.setDisable(true);
        }
    }

    public void setModeEdit(Sponsor s) {
        titleLabel.setText("✏ Modifier Sponsor (ID: " + s.getId() + ")");
        editing = s;
        result = null;
        clearErrors();

        eventIdField.setText(String.valueOf(s.getEvent_id()));
        companyField.setText(s.getCompany_name());
        contributionField.setText(String.valueOf(s.getContribution_name()));

        logoField.setText(s.getLogo_url() == null ? "" : s.getLogo_url());
        selectedLogoFile = null;

        // preview depuis URL existante
        try {
            if (s.getLogo_url() != null && !s.getLogo_url().isBlank()) {
                logoPreview.setImage(new Image(s.getLogo_url(), true));
            } else {
                logoPreview.setImage(null);
            }
        } catch (Exception e) {
            logoPreview.setImage(null);
        }

        if (logoFileLabel != null) logoFileLabel.setText("Garder logo actuel (ou choisir nouveau)");

        if (fixedEmail != null) {
            emailField.setText(fixedEmail);
            emailField.setDisable(true);
        } else {
            emailField.setText(s.getContact_email());
            emailField.setDisable(false);
        }
    }

    public Sponsor getResult() { return result; }

    @FXML
    private void onChooseLogo() {
        clearErrors();

        FileChooser fc = new FileChooser();
        fc.setTitle("Choisir un logo");
        fc.getExtensionFilters().addAll(
                new FileChooser.ExtensionFilter("Images", "*.png", "*.jpg", "*.jpeg", "*.webp")
        );

        File f = fc.showOpenDialog(getStage());
        if (f != null) {
            selectedLogoFile = f;
            if (logoFileLabel != null) logoFileLabel.setText(f.getName());

            // ✅ preview local direct
            try {
                logoPreview.setImage(new Image(f.toURI().toString(), true));
            } catch (Exception ignored) {}
        }
    }

    @FXML
    private void onSave() {
        clearErrors();

        String eventTxt = safe(eventIdField.getText());
        String company = safe(companyField.getText());
        String email = safe(emailField.getText());
        String contribTxt = safe(contributionField.getText()).replace(",", ".");

        if (fixedEmail != null && !fixedEmail.isEmpty()) email = fixedEmail;

        int eventId;
        try {
            eventId = Integer.parseInt(eventTxt);
            if (eventId <= 0) { error("event_id doit être > 0"); return; }
        } catch (NumberFormatException e) {
            error("event_id doit être un entier (ex: 1)");
            return;
        }

        if (company.isEmpty()) { error("Entreprise obligatoire"); return; }
        if (company.length() < 2) { error("Entreprise trop courte"); return; }

        if (email.isEmpty()) { error("Email obligatoire"); return; }
        if (!EMAIL_RX.matcher(email).matches()) { error("Email invalide"); return; }

        double contribution;
        try {
            contribution = Double.parseDouble(contribTxt);
            if (contribution < 0) { error("Contribution doit être >= 0"); return; }
        } catch (NumberFormatException e) {
            error("Contribution invalide (ex: 5000.00)");
            return;
        }

        // upload logo si choisi
        String logoUrlFinal = safe(logoField.getText());
        try {
            if (selectedLogoFile != null) {
                logoUrlFinal = cloud.uploadLogo(selectedLogoFile);
                logoField.setText(logoUrlFinal);
            }
        } catch (Exception ex) {
            error("Upload logo échoué: " + ex.getMessage());
            return;
        }

        Sponsor out = new Sponsor();
        if (editing != null) out.setId(editing.getId());

        out.setEvent_id(eventId);
        out.setCompany_name(company);
        out.setContact_email(email);
        out.setContribution_name(contribution);
        out.setLogo_url(logoUrlFinal.isEmpty() ? null : logoUrlFinal);

        // contrat gardé si edit
        out.setContract_url(editing == null ? null : editing.getContract_url());

        result = out;
        closeWindow();
    }

    @FXML
    private void onCancel() {
        result = null;
        closeWindow();
    }

    private String safe(String s) { return s == null ? "" : s.trim(); }
    private void error(String msg) { if (errorLabel != null) errorLabel.setText("❌ " + msg); }
    private void clearErrors() { if (errorLabel != null) errorLabel.setText(""); }

    private void closeWindow() { getStage().close(); }
    private Stage getStage() { return (Stage) eventIdField.getScene().getWindow(); }
}
