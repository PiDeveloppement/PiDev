package com.example.pidev.controller.resource;

import com.example.pidev.model.resource.ReservationResource;
import com.example.pidev.model.resource.Salle;
import com.example.pidev.model.resource.Equipement;
import com.example.pidev.service.resource.ReservationService;
import com.example.pidev.service.resource.SalleService;
import com.example.pidev.service.resource.EquipementService;

import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.collections.transformation.FilteredList;
import javafx.collections.transformation.SortedList;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.scene.layout.HBox;
import javafx.scene.paint.Color;

// Imports iText 7
import com.itextpdf.kernel.pdf.*;
import com.itextpdf.layout.Document;
import com.itextpdf.layout.element.*;
import com.itextpdf.layout.properties.*;
import com.itextpdf.kernel.colors.ColorConstants;
import com.itextpdf.io.image.ImageDataFactory;

import java.net.URL;
import java.time.LocalDateTime;
import java.util.ResourceBundle;

public class ReservationController implements Initializable {

    @FXML private ComboBox<String> typeCombo;
    @FXML private ComboBox<Object> itemCombo;
    @FXML private DatePicker startDatePicker, endDatePicker;
    @FXML private TextField quantityField;
    @FXML private Button btnValider;
    @FXML private ImageView imagePreview;
    @FXML private TextField searchField;
    @FXML private ComboBox<String> sortCombo;
    @FXML private javafx.scene.web.WebView mapWebView;

    @FXML private TableView<ReservationResource> reservationTable;
    @FXML private TableColumn<ReservationResource, String> imgCol, nameCol, typeCol;
    @FXML private TableColumn<ReservationResource, Integer> idCol, qtyCol;
    @FXML private TableColumn<ReservationResource, LocalDateTime> startCol, endCol;
    @FXML private TableColumn<ReservationResource, Void> actionCol;

    private final ObservableList<ReservationResource> masterData = FXCollections.observableArrayList();
    private final ReservationService resService = new ReservationService();
    private final SalleService salleService = new SalleService();
    private final EquipementService eqService = new EquipementService();

    private ReservationResource selectedReservation = null;

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        setupTableColumns();
        setupSearchAndSort();

        typeCombo.setItems(FXCollections.observableArrayList("SALLE", "EQUIPEMENT"));

        startDatePicker.valueProperty().addListener((obs, oldV, newV) -> refreshItemCombo());
        endDatePicker.valueProperty().addListener((obs, oldV, newV) -> refreshItemCombo());

        typeCombo.setOnAction(e -> chargerRessources());
        itemCombo.setOnAction(e -> mettreAJourApercu());

        setupItemComboDesign();
        loadTable();

        quantityField.textProperty().addListener((obs, old, newVal) -> {
            if (!newVal.matches("\\d*")) {
                quantityField.setText(newVal.replaceAll("[^\\d]", ""));
            }
        });
    }

    private void setupSearchAndSort() {
        FilteredList<ReservationResource> filteredData = new FilteredList<>(masterData, p -> true);
        searchField.textProperty().addListener((obs, oldVal, newVal) -> {
            filteredData.setPredicate(res -> {
                if (newVal == null || newVal.isEmpty()) return true;
                String filter = newVal.toLowerCase();
                return res.getResourceName().toLowerCase().contains(filter) ||
                        res.getResourceType().toLowerCase().contains(filter);
            });
        });

        SortedList<ReservationResource> sortedData = new SortedList<>(filteredData);
        sortedData.comparatorProperty().bind(reservationTable.comparatorProperty());
        reservationTable.setItems(sortedData);

        sortCombo.setOnAction(e -> {
            if ("Plus récent".equals(sortCombo.getValue())) {
                masterData.sort((a, b) -> b.getStartTimedate().compareTo(a.getStartTimedate()));
            } else if ("Plus ancien".equals(sortCombo.getValue())) {
                masterData.sort((a, b) -> a.getStartTimedate().compareTo(b.getStartTimedate()));
            }
        });
    }

    private void loadTable() {
        masterData.setAll(resService.afficher());
    }

    @FXML
    public void exportToPDF() {
        String dest = "Rapport_EventFlow_" + System.currentTimeMillis() + ".pdf";
        try {
            PdfWriter writer = new PdfWriter(dest);
            PdfDocument pdf = new PdfDocument(writer);
            Document document = new Document(pdf);

            try {
                URL logoUrl = getClass().getResource("/com/example/pidev/fxml/resource/images/logo.png");
                if (logoUrl != null) {
                    com.itextpdf.layout.element.Image pdfLogo = new com.itextpdf.layout.element.Image(
                            com.itextpdf.io.image.ImageDataFactory.create(logoUrl.toExternalForm())
                    ).setWidth(80);
                    document.add(pdfLogo.setHorizontalAlignment(HorizontalAlignment.CENTER));
                }
            } catch (Exception e) {
                System.err.println("Logo introuvable : " + e.getMessage());
            }

            document.add(new Paragraph("RAPPORT DE RÉSERVATIONS")
                    .setTextAlignment(TextAlignment.CENTER).setFontSize(18).setBold());

            Table table = new Table(new float[]{1, 3, 2, 2, 1}).useAllAvailableWidth();
            table.setMarginTop(15);

            String[] headers = {"ID", "Nom Ressource", "Date Début", "Date Fin", "Qté"};
            for (String h : headers) {
                table.addHeaderCell(new com.itextpdf.layout.element.Cell()
                        .add(new Paragraph(h).setBold().setFontColor(ColorConstants.WHITE))
                        .setBackgroundColor(ColorConstants.BLUE));
            }

            for (ReservationResource r : reservationTable.getItems()) {
                table.addCell(new com.itextpdf.layout.element.Cell().add(new Paragraph(String.valueOf(r.getId()))));
                table.addCell(new com.itextpdf.layout.element.Cell().add(new Paragraph(r.getResourceName() + " (" + r.getResourceType() + ")")));
                table.addCell(new com.itextpdf.layout.element.Cell().add(new Paragraph(r.getStartTimedate().toString())));
                table.addCell(new com.itextpdf.layout.element.Cell().add(new Paragraph(r.getEndTime().toString())));
                table.addCell(new com.itextpdf.layout.element.Cell().add(new Paragraph(String.valueOf(r.getQuantity()))));
            }

            document.add(table);
            document.close();

            new ProcessBuilder("cmd", "/c", "start", dest).start();

        } catch (Exception e) {
            e.printStackTrace();
            new Alert(Alert.AlertType.ERROR, "Erreur PDF : " + e.getMessage()).show();
        }
    }

    @FXML
    void validerAction() {
        try {
            if (itemCombo.getValue() == null || startDatePicker.getValue() == null || quantityField.getText().isEmpty()) {
                new Alert(Alert.AlertType.WARNING, "Veuillez remplir les champs obligatoires").show();
                return;
            }

            LocalDateTime s = startDatePicker.getValue().atTime(8, 0);
            LocalDateTime e = endDatePicker.getValue() == null ? s.plusHours(2) : endDatePicker.getValue().atTime(18, 0);
            Object sel = itemCombo.getValue();
            int qtySaisie = Integer.parseInt(quantityField.getText());
            int currentId = (selectedReservation == null) ? -1 : selectedReservation.getId();

            int dispo = 0;
            if (sel instanceof Salle sa) {
                dispo = resService.isSalleOccupee(sa.getId(), s, e, currentId) ? 0 : 1;
            } else if (sel instanceof Equipement eq) {
                dispo = resService.getStockTotalEquipement(eq.getId()) - resService.getStockOccupe(eq.getId(), s, e, currentId);
            }

            if (qtySaisie > dispo) {
                new Alert(Alert.AlertType.ERROR, "Action impossible. Stock disponible : " + dispo).show();
                return;
            }

            ReservationResource res = new ReservationResource(
                    currentId == -1 ? 0 : currentId,
                    typeCombo.getValue(),
                    (sel instanceof Salle sa) ? sa.getId() : null,
                    (sel instanceof Equipement eq) ? eq.getId() : null,
                    s, e, qtySaisie
            );

            if (selectedReservation == null) resService.ajouter(res);
            else resService.modifier(res);

            loadTable();
            cancelEdit();
        } catch (Exception ex) { ex.printStackTrace(); }
    }

    // --- CORRECTION DE LA MÉTHODE setupTableColumns ---
    private void setupTableColumns() {
        idCol.setCellValueFactory(new PropertyValueFactory<>("id"));
        typeCol.setCellValueFactory(new PropertyValueFactory<>("resourceType"));
        nameCol.setCellValueFactory(new PropertyValueFactory<>("resourceName"));
        qtyCol.setCellValueFactory(new PropertyValueFactory<>("quantity"));
        startCol.setCellValueFactory(new PropertyValueFactory<>("startTimedate"));
        endCol.setCellValueFactory(new PropertyValueFactory<>("endTime"));

        // Ligne cruciale : On lie la colonne au champ imagePath de ReservationResource
        imgCol.setCellValueFactory(new PropertyValueFactory<>("imagePath"));

        imgCol.setCellFactory(param -> new TableCell<>() {
            private final ImageView v = new ImageView();
            {
                v.setFitHeight(40);
                v.setFitWidth(40);
                v.setPreserveRatio(true);
            }
            @Override protected void updateItem(String path, boolean empty) {
                super.updateItem(path, empty);
                if (empty || path == null || path.isEmpty()) {
                    setGraphic(null);
                } else {
                    try {
                        // On vérifie si c'est un chemin local (file) ou une ressource
                        String finalPath = path;
                        if (path.contains(":/") || path.contains(":\\")) {
                            if (!path.startsWith("file:")) {
                                finalPath = "file:/" + path.replace("\\", "/");
                            }
                        }
                        v.setImage(new Image(finalPath, 40, 40, true, true));
                        setGraphic(v);
                    } catch (Exception ex) {
                        setGraphic(null);
                    }
                }
            }
        });

        actionCol.setCellFactory(param -> new TableCell<>() {
            private final Button edit = new Button("✎"), del = new Button("🗑");
            {
                del.setStyle("-fx-text-fill: red;");
                edit.setOnAction(e -> { selectedReservation = getTableView().getItems().get(getIndex()); premplirForm(); });
                del.setOnAction(e -> {
                    try { resService.supprimer(getTableView().getItems().get(getIndex()).getId()); loadTable(); }
                    catch (Exception ex) { ex.printStackTrace(); }
                });
            }
            @Override protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                setGraphic(empty ? null : new HBox(10, edit, del));
            }
        });
    }

    private void setupItemComboDesign() {
        itemCombo.setCellFactory(lv -> new ListCell<>() {
            @Override protected void updateItem(Object item, boolean empty) {
                super.updateItem(item, empty);
                if (empty || item == null) { setText(null); setGraphic(null); }
                else {
                    String name = ""; String path = ""; int dispo = 0;
                    LocalDateTime s = (startDatePicker.getValue() != null) ? startDatePicker.getValue().atTime(8,0) : LocalDateTime.now();
                    LocalDateTime e = (endDatePicker.getValue() != null) ? endDatePicker.getValue().atTime(18,0) : s.plusHours(2);

                    if (item instanceof Salle sa) {
                        name = sa.getName(); path = sa.getImagePath();
                        dispo = resService.isSalleOccupee(sa.getId(), s, e, selectedReservation == null ? -1 : selectedReservation.getId()) ? 0 : 1;
                    } else if (item instanceof Equipement eq) {
                        name = eq.getName(); path = eq.getImagePath();
                        dispo = resService.getStockTotalEquipement(eq.getId()) - resService.getStockOccupe(eq.getId(), s, e, selectedReservation == null ? -1 : selectedReservation.getId());
                    }
                    setText(name + " (Dispo: " + dispo + ")");
                    setTextFill(dispo <= 0 ? Color.RED : Color.BLACK);
                    setDisable(dispo <= 0);
                    if (path != null && !path.isEmpty()) {
                        try { setGraphic(new ImageView(new Image(path, 25, 25, true, true))); } catch(Exception ex){ setGraphic(null); }
                    }
                }
            }
        });
        itemCombo.setButtonCell((ListCell) itemCombo.getCellFactory().call(null));
    }

    private void refreshItemCombo() { itemCombo.setCellFactory(null); setupItemComboDesign(); }

    private void chargerRessources() {
        itemCombo.getItems().clear();
        if ("SALLE".equals(typeCombo.getValue())) itemCombo.setItems(FXCollections.observableArrayList(salleService.afficher()));
        else if ("EQUIPEMENT".equals(typeCombo.getValue())) itemCombo.setItems(FXCollections.observableArrayList(eqService.afficher()));
    }

    private void mettreAJourApercu() {
        Object sel = itemCombo.getValue();
        if (sel != null) {
            String p = (sel instanceof Salle s) ? s.getImagePath() : (sel instanceof Equipement eq ? eq.getImagePath() : null);
            if (p != null) try { imagePreview.setImage(new Image(p)); } catch(Exception ex){}
        }
    }

    private void premplirForm() {
        if (selectedReservation == null) return;
        typeCombo.setValue(selectedReservation.getResourceType());
        chargerRessources();
        startDatePicker.setValue(selectedReservation.getStartTimedate().toLocalDate());
        quantityField.setText(String.valueOf(selectedReservation.getQuantity()));
        btnValider.setText("Mettre à jour");
    }

    @FXML void cancelEdit() {
        selectedReservation = null; btnValider.setText("Réserver");
        imagePreview.setImage(null); quantityField.setText("1");
        itemCombo.getSelectionModel().clearSelection();
    }
}