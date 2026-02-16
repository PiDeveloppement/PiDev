package com.example.pidev.controller.role;

import com.example.pidev.model.role.Role;
import com.example.pidev.service.role.RoleService;
import javafx.beans.binding.Bindings;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.collections.transformation.FilteredList;
import javafx.collections.transformation.SortedList;
import javafx.event.ActionEvent;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;

import java.net.URL;
import java.sql.SQLException;
import java.util.ResourceBundle;

public class RoleController implements Initializable {

    /* ================= FIELDS ================= */

    @FXML private TextField roleNameField;  // Renommé pour correspondre au FXML
    @FXML private TextField searchField;

    @FXML private TableView<Role> roleTable;
    @FXML private TableColumn<Role, Integer> idColumn;        // Renommé
    @FXML private TableColumn<Role, String> roleNameColumn;   // Renommé
    @FXML private TableColumn<Role, Void> actionsColumn;      // Renommé

    @FXML private Button resetButton;
    @FXML private Button modifyButton;
    @FXML private Button saveButton;
    @FXML private Button cancelButton;

    @FXML private Label statsLabel;          // Nouveau
    @FXML private Label paginationLabel;     // Nouveau
    @FXML private Label formTitle;           // Nouveau
    @FXML private Label formHint;            // Nouveau
    @FXML private VBox formCard;              // Nouveau

    // Boutons de pagination
    @FXML private Button prevPageBtn;
    @FXML private Button nextPageBtn;
    @FXML private Button page1Btn;
    @FXML private Button page2Btn;
    @FXML private Button page3Btn;
    @FXML private Button page10Btn;

    private RoleService roleService;
    private ObservableList<Role> rolesList;
    private FilteredList<Role> filteredData;
    private SortedList<Role> sortedData;

    private int currentPage = 1;
    private final int rowsPerPage = 10;

    /* ================= INITIALIZE ================= */

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        try {
            System.out.println("✅ RoleController initialisé");

            roleService = new RoleService();
            rolesList = FXCollections.observableArrayList();

            initializeTableColumns();
            setupActionsColumn();
            setupSearch();
            setupPagination();

            // Setup filtered and sorted data
            filteredData = new FilteredList<>(rolesList, b -> true);
            sortedData = new SortedList<>(filteredData);
            sortedData.comparatorProperty().bind(roleTable.comparatorProperty());
            roleTable.setItems(sortedData);

            // Load roles
            loadRoles();

            // Setup selection listener
            roleTable.getSelectionModel().selectedItemProperty()
                    .addListener((obs, oldVal, newVal) -> {
                        if (newVal != null) {
                            fillForm(newVal);
                            showForm(true);
                            formTitle.setText("✏️ Modifier un rôle");
                            formHint.setText("Modifiez les informations du rôle");
                        }
                    });

            // Adjust table height
            roleTable.setFixedCellSize(40);
            roleTable.prefHeightProperty().bind(
                    Bindings.size(roleTable.getItems())
                            .multiply(roleTable.getFixedCellSize())
                            .add(40)
            );

            // Initially hide form
            showForm(false);

        } catch (SQLException e) {
            showAlert("Erreur", e.getMessage());
            e.printStackTrace();
        } catch (Exception e) {
            showAlert("Erreur", "Erreur lors de l'initialisation: " + e.getMessage());
            e.printStackTrace();
        }
    }

    private void initializeTableColumns() {
        idColumn.setCellValueFactory(new PropertyValueFactory<>("id_Role"));
        roleNameColumn.setCellValueFactory(new PropertyValueFactory<>("roleName"));
    }

    private void loadRoles() {
        rolesList.setAll(roleService.getAllRoles());
        updateStats();
        updatePagination();
    }

    private void updateStats() {
        if (statsLabel != null) {
            statsLabel.setText(rolesList.size() + " rôles");
        }
    }

    /* ================= CREATE ================= */

    @FXML
    private void addRoleButtonOnAction(ActionEvent e) {
        resetForm();
        showForm(true);
        formTitle.setText("✏️ Nouveau rôle");
        formHint.setText("Ajoutez un nouveau rôle");
        roleNameField.requestFocus();
    }

    @FXML
    private void saveRole(ActionEvent e) {
        if (!validateFields()) return;

        Role selected = roleTable.getSelectionModel().getSelectedItem();

        if (selected == null) {
            // Create new role
            Role role = new Role(roleNameField.getText());
            if (roleService.addRole(role)) {
                showAlert("Succès", "Rôle créé avec succès");
                loadRoles();
                resetForm();
                showForm(false);
            }
        } else {
            // Update existing role
            try {
                updateRole(selected);
            } catch (SQLException ex) {
                showAlert("Erreur", ex.getMessage());
            }
        }
    }

    /* ================= UPDATE ================= */

    @FXML
    private void modifyButtonOnAction(ActionEvent e) throws SQLException {
        Role selected = roleTable.getSelectionModel().getSelectedItem();

        if (selected == null) {
            showAlert("Erreur", "Sélectionnez un rôle");
            return;
        }

        updateRole(selected);
    }

    private void updateRole(Role role) throws SQLException {
        if (!validateFields()) return;

        role.setRoleName(roleNameField.getText());

        if (roleService.updateRole(role)) {
            showAlert("Succès", "Rôle modifié");
            loadRoles();
            resetForm();
            showForm(false);
        }
        searchField.clear();
    }

    /* ================= DELETE ================= */

    private void deleteRole(Role role) {
        boolean confirmed = showConfirmation(
                "Confirmer",
                "Supprimer le rôle " + role.getRoleName() + " ?"
        );

        if (!confirmed) return;

        if (roleService.deleteRole(role.getId_Role())) {
            showAlert("Succès", "Rôle supprimé");
            loadRoles();
            resetForm();
            showForm(false);
        }
    }

    /* ================= ACTION COLUMN ================= */

    private void setupActionsColumn() {
        actionsColumn.setCellFactory(param -> new TableCell<Role, Void>() {
            private final Button deleteBtn = new Button("🗑");
            private final Button editBtn = new Button("✏️");
            private final HBox container = new HBox(10, editBtn, deleteBtn);

            {
                // Style des boutons
                deleteBtn.setStyle(
                        "-fx-background-color: #ef4444;" +
                                "-fx-text-fill: white;" +
                                "-fx-background-radius: 8;" +
                                "-fx-cursor: hand;"
                );

                editBtn.setStyle(
                        "-fx-background-color: #3b82f6;" +
                                "-fx-text-fill: white;" +
                                "-fx-background-radius: 8;" +
                                "-fx-cursor: hand;"
                );

                editBtn.setOnAction(e -> {
                    Role role = getTableView().getItems().get(getIndex());
                    fillForm(role);
                    showForm(true);
                    formTitle.setText("✏️ Modifier un rôle");
                    formHint.setText("Modifiez les informations du rôle");
                });

                deleteBtn.setOnAction(e -> {
                    Role role = getTableView().getItems().get(getIndex());
                    deleteRole(role);
                });
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                setGraphic(empty ? null : container);
            }
        });
    }

    /* ================= FORM ================= */

    private void fillForm(Role role) {
        roleNameField.setText(role.getRoleName());
    }

    @FXML
    private void resetButton(ActionEvent e) {
        resetForm();
    }

    @FXML
    private void cancelEdit(ActionEvent e) {
        resetForm();
        showForm(false);
        roleTable.getSelectionModel().clearSelection();
    }

    private void resetForm() {
        roleNameField.clear();
    }

    private boolean validateFields() {
        if (roleNameField.getText().isEmpty()) {
            showAlert("Champs manquants", "Veuillez remplir tous les champs");
            return false;
        }
        return true;
    }

    private void showForm(boolean show) {
        if (formCard != null) {
            formCard.setVisible(show);
            formCard.setManaged(show);
        }
    }

    /* ================= SEARCH ================= */

    private void setupSearch() {
        if (searchField == null) return;

        searchField.textProperty().addListener((obs, oldValue, newValue) -> {
            String keyword = newValue.toLowerCase().trim();

            filteredData.setPredicate(role -> {
                if (keyword.isEmpty()) return true;
                return role.getRoleName().toLowerCase().contains(keyword);
            });

            updatePagination();
        });
    }

    /* ================= PAGINATION ================= */

    private void setupPagination() {
        if (prevPageBtn != null) {
            prevPageBtn.setOnAction(e -> {
                if (currentPage > 1) {
                    currentPage--;
                    updatePagination();
                }
            });
        }

        if (nextPageBtn != null) {
            nextPageBtn.setOnAction(e -> {
                int totalPages = (int) Math.ceil((double) filteredData.size() / rowsPerPage);
                if (currentPage < totalPages) {
                    currentPage++;
                    updatePagination();
                }
            });
        }

        // Page buttons
        if (page1Btn != null) {
            page1Btn.setOnAction(e -> {
                currentPage = 1;
                updatePagination();
            });
        }

        if (page2Btn != null) {
            page2Btn.setOnAction(e -> {
                currentPage = 2;
                updatePagination();
            });
        }

        if (page3Btn != null) {
            page3Btn.setOnAction(e -> {
                currentPage = 3;
                updatePagination();
            });
        }

        if (page10Btn != null) {
            page10Btn.setOnAction(e -> {
                int totalPages = (int) Math.ceil((double) filteredData.size() / rowsPerPage);
                currentPage = totalPages;
                updatePagination();
            });
        }
    }

    private void updatePagination() {
        if (paginationLabel == null) return;

        int totalItems = filteredData.size();
        int totalPages = (int) Math.ceil((double) totalItems / rowsPerPage);

        if (totalPages == 0) totalPages = 1;
        if (currentPage > totalPages) currentPage = totalPages;

        paginationLabel.setText("Page " + currentPage + " sur " + totalPages);

        // Update page buttons
        if (page1Btn != null) {
            page1Btn.setText(String.valueOf(currentPage));
            page2Btn.setText(String.valueOf(Math.min(currentPage + 1, totalPages)));
            page3Btn.setText(String.valueOf(Math.min(currentPage + 2, totalPages)));
            page10Btn.setText(String.valueOf(totalPages));
        }
    }

    /* ================= UTILS ================= */

    private void showAlert(String title, String msg) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION, msg);
        alert.setTitle(title);
        alert.showAndWait();
    }

    private boolean showConfirmation(String title, String msg) {
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION, msg);
        alert.setTitle(title);
        return alert.showAndWait().orElse(ButtonType.CANCEL) == ButtonType.OK;
    }

    /* ================= NAVIGATION SUPPRIMÉE ================= */
    /* Les méthodes de navigation ont été supprimées car
       la navigation est maintenant gérée par MainController */
}