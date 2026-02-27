package com.example.pidev.controller.depense;

import com.example.pidev.model.depense.Depense;
import com.example.pidev.service.translation.TranslationService;
import javafx.fxml.FXML;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.Tooltip;

import java.time.format.DateTimeFormatter;

public class DepenseCardController {

    @FXML private Label descLabel;
    @FXML private Label amountLabel;
    @FXML private Label categoryLabel;
    @FXML private Label dateLabel;
    @FXML private Label categoryPrefix;
    @FXML private Label datePrefix;
    @FXML private Button detailsBtn;
    @FXML private Button editBtn;
    @FXML private Button deleteBtn;

    private static final DateTimeFormatter FMT = DateTimeFormatter.ofPattern("yyyy-MM-dd");

    public void setData(Depense d, Runnable onDetails, Runnable onEdit, Runnable onDelete) {
        if (d == null) return;

        if (descLabel != null) descLabel.setText(nvl(d.getDescription(), "—"));
        if (amountLabel != null) amountLabel.setText(String.format("%,.2f DT", d.getAmount()));
        if (categoryLabel != null) categoryLabel.setText(nvl(d.getCategory(), "—"));
        if (dateLabel != null) dateLabel.setText(d.getExpense_date() == null ? "—" : FMT.format(d.getExpense_date()));

        if (detailsBtn != null) detailsBtn.setOnAction(e -> { if (onDetails != null) onDetails.run(); });
        if (editBtn != null) editBtn.setOnAction(e -> { if (onEdit != null) onEdit.run(); });
        if (deleteBtn != null) deleteBtn.setOnAction(e -> { if (onDelete != null) onDelete.run(); });

        translateUI();
    }

    private void translateUI() {
        if (TranslationService.getCurrentLang().equals("fr")) return;
        if (categoryPrefix != null) categoryPrefix.setText(TranslationService.translate("Catégorie") + ":");
        if (datePrefix != null) datePrefix.setText(TranslationService.translate("Date") + ":");
        if (detailsBtn != null) detailsBtn.setText(TranslationService.translate("Détails"));
        if (editBtn != null) editBtn.setText(TranslationService.translate("Modifier"));
        if (deleteBtn != null) deleteBtn.setText(TranslationService.translate("Supprimer"));
    }

    private String nvl(String s, String def) {
        return (s == null || s.isBlank()) ? def : s;
    }
}