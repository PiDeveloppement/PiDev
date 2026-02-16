package com.example.pidev.controller.user;

import com.example.pidev.MainController;
import com.example.pidev.model.role.Role;
import com.example.pidev.model.user.UserModel;
import com.example.pidev.service.role.RoleService;
import com.example.pidev.service.user.UserService;

import javafx.beans.binding.Bindings;
import javafx.beans.property.SimpleStringProperty;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.collections.transformation.FilteredList;
import javafx.collections.transformation.SortedList;
import javafx.event.ActionEvent;
import javafx.fxml.*;
import javafx.scene.Node;
import javafx.scene.Parent;
import javafx.scene.Scene;
import javafx.scene.control.*;
import javafx.scene.control.cell.PropertyValueFactory;
import javafx.scene.layout.HBox;
import javafx.stage.Stage;
import javafx.scene.control.Tooltip;
import javafx.scene.control.Alert;
import javafx.scene.control.ButtonType;
import javafx.scene.control.TableCell;
import javafx.scene.layout.HBox;

import java.net.URL;
import java.sql.SQLException;
import java.util.ResourceBundle;

public class UserController implements Initializable {

    /* ================= FIELDS ================= */

    @FXML private TextField searchField;

    private MainController mainController;
    @FXML private TableView<UserModel> userTable;
    @FXML private TableColumn<UserModel, String> firstname_column;
    @FXML private TableColumn<UserModel, String> lastname_column;
    @FXML private TableColumn<UserModel, String> email_column;
    @FXML private TableColumn<UserModel, String> faculte_column;
    @FXML private TableColumn<UserModel, String> password_column;
    @FXML private TableColumn<UserModel, String> role_column;
    @FXML private TableColumn<UserModel, Void> actions_column;

    @FXML private ComboBox<String> faculteFilterCombo;
    @FXML private ComboBox<String> roleFilterCombo;

    private UserService userService;
    private RoleService roleService;
    private ObservableList<UserModel> usersList;
    private FilteredList<UserModel> filteredData;
    private SortedList<UserModel> sortedData;
    @FXML private Button prevPageBtn;
    @FXML private Button nextPageBtn;
    @FXML private Button page1Btn;
    @FXML private Button page2Btn;
    @FXML private Button page3Btn;
    @FXML private Button page10Btn;
    @FXML private Label paginationLabel;

    private int currentPage = 1;
    private final int rowsPerPage = 10;

    /* ================= INITIALIZE ================= */

    @Override
    public void initialize(URL url, ResourceBundle rb) {
        try {
            userService = new UserService();
            roleService = new RoleService();
            usersList = FXCollections.observableArrayList();

            initializeTableColumns();

            // Setup filtered et sorted data
            filteredData = new FilteredList<>(usersList, b -> true);
            sortedData = new SortedList<>(filteredData);
            sortedData.comparatorProperty().bind(userTable.comparatorProperty());
            userTable.setItems(sortedData);

            // Charger les données
            loadUsers();

            // === CHARGER LES LISTES POUR LES FILTRES ===
            loadFaculteFilterList();
            loadRoleFilterList();

            setupSearch();
            setupActionsColumn();

            userTable.setFixedCellSize(40);
            userTable.prefHeightProperty().bind(
                    Bindings.size(userTable.getItems())
                            .multiply(userTable.getFixedCellSize())
                            .add(40)
            );

        } catch (SQLException e) {
            showAlert("Erreur", e.getMessage());
        }
    }

    private void initializeTableColumns() {
        firstname_column.setCellValueFactory(new PropertyValueFactory<>("first_Name"));
        lastname_column.setCellValueFactory(new PropertyValueFactory<>("last_Name"));
        email_column.setCellValueFactory(new PropertyValueFactory<>("email"));
        faculte_column.setCellValueFactory(new PropertyValueFactory<>("faculte"));
        password_column.setCellValueFactory(new PropertyValueFactory<>("password"));

        role_column.setCellValueFactory(cell -> {
            Role r = cell.getValue().getRole();
            return new SimpleStringProperty(r != null ? r.getRoleName() : "");
        });
    }

    private void loadUsers() {
        try {
            usersList.setAll(userService.getAllUsers());
            loadFaculteFilterList();
        } catch (Exception e) {
            showAlert("Erreur", "Impossible de charger les utilisateurs: " + e.getMessage());
            e.printStackTrace();
        }
    }

    /* ================= DELETE ================= */

    private void deleteUser(UserModel user) {
        boolean confirmed = showConfirmation(
                "Confirmer",
                "Supprimer " + user.getFirst_Name() + " ?"
        );

        if (!confirmed) return;

        if (userService.deleteUser(user.getId_User())) {
            showAlert("Succès", "Utilisateur supprimé");
            loadUsers();
        }
    }

    /* ================= ACTION COLUMN ================= */
    private void setupActionsColumn() {
        actions_column.setCellFactory(param -> new TableCell<UserModel, Void>() {
            private final Button editBtn = new Button();
            private final Button deleteBtn = new Button();
            private final HBox container = new HBox(10, editBtn, deleteBtn);

            {
                // Bouton Modifier
                Label editIcon = new Label("✏️");
                editIcon.setStyle("-fx-font-size: 16px; -fx-text-fill: white;");
                editBtn.setGraphic(editIcon);
                editBtn.setStyle(
                        "-fx-background-color: #3b82f6;" +        // Bleu
                                "-fx-background-radius: 8;" +
                                "-fx-text-fill: white;" +
                                "-fx-font-size: 13px;" +
                                "-fx-font-weight: bold;" +
                                "-fx-padding: 8 12 8 12;" +
                                "-fx-cursor: hand;" +
                                "-fx-effect: dropshadow(gaussian, rgba(59, 130, 246, 0.3), 4, 0, 0, 2);" +
                                "-fx-border: none;"
                );
                editBtn.setOnMouseEntered(e ->
                        editBtn.setStyle(
                                "-fx-background-color: #2563eb;" +
                                        "-fx-background-radius: 8;" +
                                        "-fx-text-fill: white;" +
                                        "-fx-font-size: 13px;" +
                                        "-fx-font-weight: bold;" +
                                        "-fx-padding: 8 12 8 12;" +
                                        "-fx-cursor: hand;" +
                                        "-fx-effect: dropshadow(gaussian, rgba(37, 99, 235, 0.4), 6, 0, 0, 3);" +
                                        "-fx-border: none;"
                        )
                );
                editBtn.setOnMouseExited(e ->
                        editBtn.setStyle(
                                "-fx-background-color: #3b82f6;" +
                                        "-fx-background-radius: 8;" +
                                        "-fx-text-fill: white;" +
                                        "-fx-font-size: 13px;" +
                                        "-fx-font-weight: bold;" +
                                        "-fx-padding: 8 12 8 12;" +
                                        "-fx-cursor: hand;" +
                                        "-fx-effect: dropshadow(gaussian, rgba(59, 130, 246, 0.3), 4, 0, 0, 2);" +
                                        "-fx-border: none;"
                        )
                );
                Tooltip editTooltip = new Tooltip("Modifier");
                editTooltip.setStyle("-fx-background-color: #1f2937; -fx-text-fill: white; -fx-font-size: 12px;");
                editBtn.setTooltip(editTooltip);

                editBtn.setOnAction(e -> {
                    UserModel user = getTableView().getItems().get(getIndex());
                    openEditPage(user); // MODIFIÉ : Appelle openEditPage au lieu de openEditWindow
                });

                // Bouton Supprimer
                Label deleteIcon = new Label("🗑");
                deleteIcon.setStyle("-fx-font-size: 16px; -fx-text-fill: white;");
                deleteBtn.setGraphic(deleteIcon);
                deleteBtn.setStyle(
                        "-fx-background-color: #ef4444;" +
                                "-fx-background-radius: 8;" +
                                "-fx-text-fill: white;" +
                                "-fx-font-size: 13px;" +
                                "-fx-font-weight: bold;" +
                                "-fx-padding: 8 12 8 12;" +
                                "-fx-cursor: hand;" +
                                "-fx-effect: dropshadow(gaussian, rgba(239, 68, 68, 0.3), 4, 0, 0, 2);" +
                                "-fx-border: none;"
                );

                // Effet au survol pour le bouton Supprimer
                deleteBtn.setOnMouseEntered(e ->
                        deleteBtn.setStyle(
                                "-fx-background-color: #dc2626;" +
                                        "-fx-background-radius: 8;" +
                                        "-fx-text-fill: white;" +
                                        "-fx-font-size: 13px;" +
                                        "-fx-font-weight: bold;" +
                                        "-fx-padding: 8 12 8 12;" +
                                        "-fx-cursor: hand;" +
                                        "-fx-effect: dropshadow(gaussian, rgba(220, 38, 38, 0.4), 6, 0, 0, 3);" +
                                        "-fx-border: none;"
                        )
                );

                deleteBtn.setOnMouseExited(e ->
                        deleteBtn.setStyle(
                                "-fx-background-color: #ef4444;" +
                                        "-fx-background-radius: 8;" +
                                        "-fx-text-fill: white;" +
                                        "-fx-font-size: 13px;" +
                                        "-fx-font-weight: bold;" +
                                        "-fx-padding: 8 12 8 12;" +
                                        "-fx-cursor: hand;" +
                                        "-fx-effect: dropshadow(gaussian, rgba(239, 68, 68, 0.3), 4, 0, 0, 2);" +
                                        "-fx-border: none;"
                        )
                );

                Tooltip deleteTooltip = new Tooltip("Supprimer");
                deleteTooltip.setStyle("-fx-background-color: #1f2937; -fx-text-fill: white; -fx-font-size: 12px;");
                deleteBtn.setTooltip(deleteTooltip);

                deleteBtn.setOnAction(e -> {
                    UserModel user = getTableView().getItems().get(getIndex());
                    confirmAndDelete(user);
                });
            }

            @Override
            protected void updateItem(Void item, boolean empty) {
                super.updateItem(item, empty);
                if (empty) {
                    setGraphic(null);
                } else {
                    container.setAlignment(javafx.geometry.Pos.CENTER);
                    setGraphic(container);
                }
            }
        });
    }

    /* ================= NOUVELLE MÉTHODE : openEditPage ================= */
    private void openEditPage(UserModel user) {
        if (mainController != null) {
            mainController.loadEditUserPage(user);
        } else {
            showAlert("Erreur", "Impossible d'ouvrir la page de modification");
        }
    }

    /* ================= UTILS ================= */

    private void showAlert(String title, String msg) {
        new Alert(Alert.AlertType.INFORMATION, msg).showAndWait();
    }

    private boolean showConfirmation(String title, String msg) {
        return new Alert(Alert.AlertType.CONFIRMATION, msg)
                .showAndWait().orElse(ButtonType.CANCEL) == ButtonType.OK;
    }

    /* ================= NAVIGATION ================= */

    @FXML
    private void goToRole(ActionEvent event) {
        try {
            Parent root = FXMLLoader.load(
                    getClass().getResource("/com/example/pidev/fxml/role/role.fxml")
            );
            Stage stage = (Stage) ((Node) event.getSource()).getScene().getWindow();
            stage.setScene(new Scene(root));
            stage.show();
        } catch (Exception ignored) {}
    }

    private void setupSearch() {
        searchField.textProperty().addListener((obs, oldValue, newValue) -> {
            String keyword = newValue.toLowerCase().trim();

            filteredData.setPredicate(user -> {
                if (keyword.isEmpty()) return true;

                boolean matchesFirstName = user.getFirst_Name().toLowerCase().contains(keyword);
                boolean matchesLastName = user.getLast_Name().toLowerCase().contains(keyword);
                boolean matchesEmail = user.getEmail().toLowerCase().contains(keyword);
                boolean matchesFaculte = user.getFaculte().toLowerCase().contains(keyword);

                boolean matchesRole = user.getRole() != null &&
                        user.getRole().getRoleName().toLowerCase().contains(keyword);

                return matchesFirstName || matchesLastName || matchesEmail || matchesFaculte || matchesRole;
            });
        });
    }

    /**
     * Charge la liste unique des facultés depuis la base de données
     */
    private void loadFaculteFilterList() {
        try {
            ObservableList<String> faculteList = FXCollections.observableArrayList();
            faculteList.addAll(userService.getAllFacultes());
            faculteFilterCombo.setItems(faculteList);
        } catch (Exception e) {
            System.err.println("Erreur lors du chargement des facultés: " + e.getMessage());
            e.printStackTrace();
        }
    }

    /**
     * Charge la liste unique des rôles depuis la base de données
     */
    private void loadRoleFilterList() {
        try {
            ObservableList<String> roleList = FXCollections.observableArrayList();
            roleList.addAll(roleService.getAllRoleNames());
            roleFilterCombo.setItems(roleList);
        } catch (Exception e) {
            System.err.println("Erreur lors du chargement des rôles: " + e.getMessage());
            e.printStackTrace();
        }
    }

    @FXML
    private void filterByFaculte(ActionEvent event) {
        String selectedFaculte = faculteFilterCombo.getValue();
        String currentSearch = searchField.getText().toLowerCase().trim();
        String selectedRole = roleFilterCombo.getValue();

        filteredData.setPredicate(user -> {
            boolean matchesFaculte = (selectedFaculte == null || selectedFaculte.isEmpty()) ||
                    user.getFaculte().equalsIgnoreCase(selectedFaculte);

            boolean matchesRole = (selectedRole == null || selectedRole.isEmpty()) ||
                    (user.getRole() != null &&
                            user.getRole().getRoleName().equalsIgnoreCase(selectedRole));

            boolean matchesSearch = currentSearch.isEmpty() ||
                    user.getFirst_Name().toLowerCase().contains(currentSearch) ||
                    user.getLast_Name().toLowerCase().contains(currentSearch) ||
                    user.getEmail().toLowerCase().contains(currentSearch) ||
                    user.getFaculte().toLowerCase().contains(currentSearch) ||
                    (user.getRole() != null && user.getRole().getRoleName().toLowerCase().contains(currentSearch));

            return matchesFaculte && matchesRole && matchesSearch;
        });
    }

    @FXML
    private void filterByRole(ActionEvent event) {
        String selectedRole = roleFilterCombo.getValue();
        String currentSearch = searchField.getText().toLowerCase().trim();
        String selectedFaculte = faculteFilterCombo.getValue();

        filteredData.setPredicate(user -> {
            boolean matchesRole = (selectedRole == null || selectedRole.isEmpty()) ||
                    (user.getRole() != null &&
                            user.getRole().getRoleName().equalsIgnoreCase(selectedRole));

            boolean matchesFaculte = (selectedFaculte == null || selectedFaculte.isEmpty()) ||
                    user.getFaculte().equalsIgnoreCase(selectedFaculte);

            boolean matchesSearch = currentSearch.isEmpty() ||
                    user.getFirst_Name().toLowerCase().contains(currentSearch) ||
                    user.getLast_Name().toLowerCase().contains(currentSearch) ||
                    user.getEmail().toLowerCase().contains(currentSearch) ||
                    user.getFaculte().toLowerCase().contains(currentSearch) ||
                    (user.getRole() != null && user.getRole().getRoleName().toLowerCase().contains(currentSearch));

            return matchesRole && matchesFaculte && matchesSearch;
        });
    }

    @FXML
    private void resetFilters(ActionEvent event) {
        faculteFilterCombo.getSelectionModel().clearSelection();
        roleFilterCombo.getSelectionModel().clearSelection();
        searchField.clear();
        filteredData.setPredicate(user -> true);
    }

    private void setupPagination() {
        int totalItems = filteredData.size();
        int totalPages = (int) Math.ceil((double) totalItems / rowsPerPage);
        paginationLabel.setText("Page " + currentPage + " sur " + totalPages);

        int fromIndex = (currentPage - 1) * rowsPerPage;
        int toIndex = Math.min(fromIndex + rowsPerPage, totalItems);

        if (fromIndex <= toIndex) {
            userTable.setItems(FXCollections.observableArrayList(
                    filteredData.subList(fromIndex, toIndex)
            ));
        }
    }

    /* ================= ANCIENNE MÉTHODE SUPPRIMÉE ================= */
    // La méthode openEditWindow a été supprimée car nous utilisons maintenant openEditPage

    private void confirmAndDelete(UserModel user) {
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION);
        alert.setTitle("Confirmation de suppression");
        alert.setHeaderText("Supprimer l'utilisateur");
        alert.setContentText("Êtes-vous sûr de vouloir supprimer " + user.getFirst_Name() + " ?");

        DialogPane dialogPane = alert.getDialogPane();
        dialogPane.setStyle("-fx-background-color: white; -fx-font-size: 14px;");

        Button okButton = (Button) dialogPane.lookupButton(ButtonType.OK);
        okButton.setStyle("-fx-background-color: #ef4444; -fx-text-fill: white; -fx-font-weight: bold; -fx-padding: 8 16; -fx-background-radius: 6;");

        Button cancelButton = (Button) dialogPane.lookupButton(ButtonType.CANCEL);
        cancelButton.setStyle("-fx-background-color: #e5e7eb; -fx-text-fill: #374151; -fx-padding: 8 16; -fx-background-radius: 6;");

        alert.showAndWait().ifPresent(response -> {
            if (response == ButtonType.OK) {
                deleteUser(user);
            }
        });
    }

    public void setMainController(MainController mainController) {
        this.mainController = mainController;
    }
}