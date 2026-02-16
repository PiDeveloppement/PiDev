package com.example.pidev.controller.sponsor;

import com.example.pidev.model.sponsor.Sponsor;
import com.example.pidev.service.sponsor.SponsorService;
import javafx.beans.property.*;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.collections.transformation.FilteredList;
import javafx.collections.transformation.SortedList;
import javafx.fxml.FXML;
import javafx.geometry.Pos;
import javafx.scene.control.*;
import javafx.scene.control.ContentDisplay;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.scene.layout.HBox;

import java.io.InputStream;

public class SponsorListController {

    @FXML private TableView<Sponsor> table;
    @FXML private TableColumn<Sponsor, Integer> colId;
    @FXML private TableColumn<Sponsor, Integer> colEventId;
    @FXML private TableColumn<Sponsor, String> colCompany;
    @FXML private TableColumn<Sponsor, Double> colContribution;
    @FXML private TableColumn<Sponsor, String> colEmail;
    @FXML private TableColumn<Sponsor, Void> colActions;

    @FXML private TextField searchField;

    private final SponsorService sponsorService = new SponsorService();
    private final ObservableList<Sponsor> baseList = FXCollections.observableArrayList();
    private FilteredList<Sponsor> filtered;

    private static final String ICON_EDIT   = "/com/example/pidev/icons/edit.png";
    private static final String ICON_DELETE = "/com/example/pidev/icons/delete.png";

    @FXML
    private void initialize() {
        setupColumns();
        setupActions();
        setupFilter();

        refresh();
    }

    private void setupColumns() {
        colId.setCellValueFactory(c -> new SimpleIntegerProperty(c.getValue().getId()).asObject());
        colEventId.setCellValueFactory(c -> new SimpleIntegerProperty(c.getValue().getEvent_id()).asObject());
        colCompany.setCellValueFactory(c -> new SimpleStringProperty(c.getValue().getCompany_name()));
        colContribution.setCellValueFactory(c -> new SimpleDoubleProperty(c.getValue().getContribution_name()).asObject());
        colEmail.setCellValueFactory(c -> new SimpleStringProperty(c.getValue().getContact_email()));
    }

    private void setupActions() {
        colActions.setCellFactory(col -> new TableCell<>() {
            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                if (empty) { setGraphic(null); return; }

                Sponsor s = getTableView().getItems().get(getIndex());

                Button editBtn = makeIconButton(ICON_EDIT, "#e0f2fe", "#0ea5e9");
                Button delBtn  = makeIconButton(ICON_DELETE, "#fee2e2", "#ef4444");

                editBtn.setTooltip(new Tooltip("Modifier"));
                delBtn.setTooltip(new Tooltip("Supprimer"));

                editBtn.setOnAction(e -> handleEdit(s));
                delBtn.setOnAction(e -> handleDelete(s));

                HBox box = new HBox(8, editBtn, delBtn);
                box.setAlignment(Pos.CENTER);
                setGraphic(box);
            }
        });
    }

    private Button makeIconButton(String path, String bg, String border) {
        Button b = new Button();
        b.setMinSize(36, 32);
        b.setPrefSize(36, 32);
        b.setMaxSize(36, 32);

        ImageView iv = loadIcon(path, 16);
        if (iv != null) {
            b.setGraphic(iv);
            b.setContentDisplay(ContentDisplay.GRAPHIC_ONLY);
        } else {
            b.setText("?");
        }

        b.setStyle(
                "-fx-background-color: " + bg + ";" +
                        "-fx-background-radius: 8;" +
                        "-fx-border-radius: 8;" +
                        "-fx-border-color: " + border + ";" +
                        "-fx-border-width: 1;" +
                        "-fx-cursor: hand;"
        );
        return b;
    }

    private ImageView loadIcon(String path, int size) {
        try (InputStream is = getClass().getResourceAsStream(path)) {
            if (is == null) return null;
            ImageView iv = new ImageView(new Image(is));
            iv.setFitWidth(size);
            iv.setFitHeight(size);
            iv.setPreserveRatio(true);
            return iv;
        } catch (Exception e) {
            return null;
        }
    }

    private void setupFilter() {
        filtered = new FilteredList<>(baseList, x -> true);

        if (searchField != null) {
            searchField.textProperty().addListener((obs, o, n) -> {
                String q = n == null ? "" : n.toLowerCase();

                filtered.setPredicate(s ->
                        q.isEmpty()
                                || String.valueOf(s.getId()).contains(q)
                                || (s.getCompany_name() != null && s.getCompany_name().toLowerCase().contains(q))
                                || (s.getContact_email() != null && s.getContact_email().toLowerCase().contains(q))
                );
            });
        }

        SortedList<Sponsor> sorted = new SortedList<>(filtered);
        sorted.comparatorProperty().bind(table.comparatorProperty());
        table.setItems(sorted);
    }

    private void refresh() {
        baseList.setAll(sponsorService.getAllSponsors());
    }

    private void handleEdit(Sponsor s) {
        System.out.println("Modifier sponsor ID = " + s.getId());
    }

    private void handleDelete(Sponsor s) {
        Alert confirm = new Alert(Alert.AlertType.CONFIRMATION);
        confirm.setHeaderText("Supprimer sponsor");
        confirm.setContentText("Supprimer sponsor ID = " + s.getId() + " ?");
        confirm.showAndWait().ifPresent(btn -> {
            if (btn == ButtonType.OK) {
                sponsorService.deleteSponsor(s.getId());
                refresh();
            }
        });
    }
}
