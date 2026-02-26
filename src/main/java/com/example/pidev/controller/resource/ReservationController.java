package com.example.pidev.controller.resource;

import com.example.pidev.MainController;
import com.example.pidev.model.resource.ReservationResource;
import com.example.pidev.service.resource.ReservationService;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.collections.transformation.FilteredList;
import javafx.collections.transformation.SortedList;
import javafx.fxml.FXML;
import javafx.fxml.FXMLLoader;
import javafx.fxml.Initializable;
import javafx.scene.Parent;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Region;
import com.itextpdf.kernel.pdf.*;
import com.itextpdf.layout.Document;
import com.itextpdf.layout.element.*;
import com.itextpdf.layout.properties.*;
import com.itextpdf.kernel.colors.ColorConstants;
import java.net.URL;
import java.time.LocalDateTime;
import java.util.ResourceBundle;

public class ReservationController implements Initializable {

    @FXML private TextField searchField;
    @FXML private ComboBox<String> sortCombo;
    @FXML private TableView<ReservationResource> reservationTable;
    @FXML private TableColumn<ReservationResource, String> imgCol, nameCol, typeCol;
    @FXML private TableColumn<ReservationResource, Integer> qtyCol; // idCol supprimé ici
    @FXML private TableColumn<ReservationResource, LocalDateTime> startCol, endCol;
    @FXML private TableColumn<ReservationResource, Void> actionCol;

    private final ObservableList<ReservationResource> masterData = FXCollections.observableArrayList();
    private final ReservationService resService = new ReservationService();

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        setupTableColumns();
        setupSearchAndSort();
        loadTable();
    }

    private void loadTable() {
        masterData.setAll(resService.afficher());
    }

    private void setupTableColumns() {
        typeCol.setCellValueFactory(new PropertyValueFactory<>("resourceType"));
        nameCol.setCellValueFactory(new PropertyValueFactory<>("resourceName"));
        qtyCol.setCellValueFactory(new PropertyValueFactory<>("quantity"));
        startCol.setCellValueFactory(new PropertyValueFactory<>("startTimedate"));
        endCol.setCellValueFactory(new PropertyValueFactory<>("endTime"));
        imgCol.setCellValueFactory(new PropertyValueFactory<>("imagePath"));

        imgCol.setCellFactory(param -> new TableCell<>() {
            private final ImageView v = new ImageView();
            { v.setFitHeight(40); v.setFitWidth(40); v.setPreserveRatio(true); }
            @Override protected void updateItem(String path, boolean empty) {
                super.updateItem(path, empty);
                if (empty || path == null) setGraphic(null);
                else {
                    try {
                        String finalPath = path;
                        if (path.contains(":/") || path.contains(":\\")) {
                            if (!path.startsWith("file:")) finalPath = "file:/" + path.replace("\\", "/");
                        }
                        v.setImage(new Image(finalPath, 40, 40, true, true));
                        setGraphic(v);
                    } catch (Exception ex) { setGraphic(null); }
                }
            }
        });

        actionCol.setCellFactory(param -> new TableCell<>() {
            private final Button edit = new Button("✎"), del = new Button("🗑");
            {
                del.setStyle("-fx-text-fill: red;");
                edit.setOnAction(e -> goToForm(getTableView().getItems().get(getIndex())));
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

    @FXML
    void goToAdd() { goToForm(null); }

    private void goToForm(ReservationResource res) {
        try {
            FXMLLoader loader = new FXMLLoader(getClass().getResource("/com/example/pidev/fxml/resource/reservation_form.fxml"));
            Parent root = loader.load();
            ReservationFormController controller = loader.getController();
            controller.setReservationToEdit(res);
            MainController.getInstance().setContent(root);
        } catch (Exception e) { e.printStackTrace(); }
    }

    private void setupSearchAndSort() {
        FilteredList<ReservationResource> filteredData = new FilteredList<>(masterData, p -> true);
        searchField.textProperty().addListener((obs, oldVal, newVal) -> {
            filteredData.setPredicate(res -> {
                if (newVal == null || newVal.isEmpty()) return true;
                String filter = newVal.toLowerCase();
                return res.getResourceName().toLowerCase().contains(filter) || res.getResourceType().toLowerCase().contains(filter);
            });
        });
        SortedList<ReservationResource> sortedData = new SortedList<>(filteredData);
        sortedData.comparatorProperty().bind(reservationTable.comparatorProperty());
        reservationTable.setItems(sortedData);
        sortCombo.setOnAction(e -> {
            if ("Plus récent".equals(sortCombo.getValue())) masterData.sort((a, b) -> b.getStartTimedate().compareTo(a.getStartTimedate()));
            else if ("Plus ancien".equals(sortCombo.getValue())) masterData.sort((a, b) -> a.getStartTimedate().compareTo(b.getStartTimedate()));
        });
    }
    @FXML
    public void exportToPDF() {
        String dest = "Rapport_Reservations.pdf";
        try {
            PdfWriter writer = new PdfWriter(dest);
            PdfDocument pdf = new PdfDocument(writer);
            Document document = new Document(pdf);

            document.add(new Paragraph("RAPPORT DE RÉSERVATIONS")
                    .setTextAlignment(TextAlignment.CENTER)
                    .setFontSize(18)
                    .setBold());

            Table table = new Table(new float[]{3, 2, 2, 1}).useAllAvailableWidth();
            String[] headers = {"Nom Ressource", "Date Début", "Date Fin", "Qté"};

            for (String h : headers) {
                // Utilisation du nom complet pour éviter l'ambiguïté
                table.addHeaderCell(new com.itextpdf.layout.element.Cell()
                        .add(new Paragraph(h).setBold().setFontColor(ColorConstants.WHITE))
                        .setBackgroundColor(ColorConstants.BLUE));
            }

            for (ReservationResource r : masterData) {
                table.addCell(new com.itextpdf.layout.element.Cell().add(new Paragraph(r.getResourceName())));
                table.addCell(new com.itextpdf.layout.element.Cell().add(new Paragraph(r.getStartTimedate().toString())));
                table.addCell(new com.itextpdf.layout.element.Cell().add(new Paragraph(r.getEndTime().toString())));
                table.addCell(new com.itextpdf.layout.element.Cell().add(new Paragraph(String.valueOf(r.getQuantity()))));
            }

            document.add(table);
            document.close();
            new ProcessBuilder("cmd", "/c", "start", dest).start();
        } catch (Exception e) {
            e.printStackTrace();
        }
    }
}