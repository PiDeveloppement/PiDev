package com.example.pidev.controller.resource;

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
import javafx.stage.Stage;

import com.itextpdf.kernel.pdf.*;
import com.itextpdf.layout.Document;
import com.itextpdf.layout.element.*;
import com.itextpdf.layout.properties.*;
import com.itextpdf.kernel.colors.ColorConstants;

import java.io.IOException;
import java.net.URL;
import java.time.LocalDateTime;
import java.util.ResourceBundle;

public class ReservationController implements Initializable {

    @FXML private TableView<ReservationResource> reservationTable;
    @FXML private TableColumn<ReservationResource, Integer> idCol, qtyCol;
    @FXML private TableColumn<ReservationResource, String> nameCol, typeCol, imgCol;
    @FXML private TableColumn<ReservationResource, LocalDateTime> startCol, endCol;
    @FXML private TableColumn<ReservationResource, Void> actionCol;
    @FXML private TextField searchField;

    private final ObservableList<ReservationResource> masterData = FXCollections.observableArrayList();
    private final ReservationService resService = new ReservationService();

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        setupTableColumns();
        setupSearch();
        loadTable();
    }

    private void loadTable() {
        try {
            masterData.setAll(resService.afficher());
            reservationTable.setItems(masterData);
        } catch (Exception e) {
            System.err.println("Erreur chargement données : " + e.getMessage());
        }
    }

    private void setupTableColumns() {
        idCol.setCellValueFactory(new PropertyValueFactory<>("id"));
        typeCol.setCellValueFactory(new PropertyValueFactory<>("resourceType"));
        nameCol.setCellValueFactory(new PropertyValueFactory<>("resourceName"));
        qtyCol.setCellValueFactory(new PropertyValueFactory<>("quantity"));
        startCol.setCellValueFactory(new PropertyValueFactory<>("startTimedate"));
        endCol.setCellValueFactory(new PropertyValueFactory<>("endTime"));

        imgCol.setCellFactory(param -> new TableCell<>() {
            private final ImageView v = new ImageView();
            { v.setFitHeight(40); v.setFitWidth(40); v.setPreserveRatio(true); }
            @Override protected void updateItem(String path, boolean empty) {
                super.updateItem(path, empty);
                if (empty || path == null) setGraphic(null);
                else {
                    try {
                        v.setImage(new Image(path, 40, 40, true, true));
                        setGraphic(v);
                    } catch (Exception ex) { setGraphic(null); }
                }
            }
        });

        actionCol.setCellFactory(param -> new TableCell<>() {
            private final Button edit = new Button("✎"), del = new Button("🗑");
            {
                del.setStyle("-fx-text-fill: red; -fx-cursor: hand;");
                edit.setStyle("-fx-cursor: hand;");
                edit.setOnAction(e -> goToForm(getTableView().getItems().get(getIndex())));
                del.setOnAction(e -> {
                    ReservationResource selected = getTableView().getItems().get(getIndex());
                    try {
                        resService.supprimer(selected.getId());
                        loadTable();
                    } catch (Exception ex) {
                        new Alert(Alert.AlertType.ERROR, "Erreur suppression : " + ex.getMessage()).show();
                    }
                });
            }
            @Override protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                setGraphic(empty ? null : new HBox(10, edit, del));
            }
        });
    }

    private void setupSearch() {
        FilteredList<ReservationResource> filteredData = new FilteredList<>(masterData, p -> true);
        searchField.textProperty().addListener((obs, oldV, newV) -> {
            filteredData.setPredicate(res -> {
                if (newV == null || newV.isEmpty()) return true;
                String filter = newV.toLowerCase();
                return res.getResourceName().toLowerCase().contains(filter) || res.getResourceType().toLowerCase().contains(filter);
            });
        });
        SortedList<ReservationResource> sortedData = new SortedList<>(filteredData);
        sortedData.comparatorProperty().bind(reservationTable.comparatorProperty());
        reservationTable.setItems(sortedData);
    }

    @FXML void exportToPDF() {
        String dest = "Rapport_Reservations.pdf";
        try {
            PdfWriter writer = new PdfWriter(dest);
            PdfDocument pdf = new PdfDocument(writer);
            Document document = new Document(pdf);
            document.add(new Paragraph("RAPPORT DES RÉSERVATIONS").setBold().setFontSize(18));
            Table table = new Table(new float[]{1, 3, 2, 2, 1}).useAllAvailableWidth();
            table.addHeaderCell("ID"); table.addHeaderCell("Ressource"); table.addHeaderCell("Début"); table.addHeaderCell("Fin"); table.addHeaderCell("Qté");
            for (ReservationResource r : reservationTable.getItems()) {
                table.addCell(String.valueOf(r.getId())); table.addCell(r.getResourceName());
                table.addCell(r.getStartTimedate().toString()); table.addCell(r.getEndTime().toString());
                table.addCell(String.valueOf(r.getQuantity()));
            }
            document.add(table); document.close();
            new ProcessBuilder("cmd", "/c", "start", dest).start();
        } catch (Exception e) { e.printStackTrace(); }
    }

    @FXML void goToAddForm() { goToForm(null); }

    private void goToForm(ReservationResource res) {
        try {
            URL fxmlUrl = getClass().getResource("/com/example/pidev/fxml/resource/reservation_form.fxml");
            if (fxmlUrl == null) throw new IOException("Fichier reservation_form.fxml introuvable");

            FXMLLoader loader = new FXMLLoader(fxmlUrl);
            Parent root = loader.load();
            ReservationFormController controller = loader.getController();
            controller.setReservationToEdit(res);

            Stage stage = (Stage) reservationTable.getScene().getWindow();
            stage.getScene().setRoot(root);
        } catch (IOException e) { e.printStackTrace(); }
    }
}