package com.example.pidev.controller.user;

import com.example.pidev.MainController;
import com.example.pidev.model.role.Role;
import com.example.pidev.model.user.UserModel;
import com.example.pidev.service.event.EventService;  // ← AJOUTER CET IMPORT
import com.example.pidev.service.role.RoleService;
import com.example.pidev.service.user.UserService;
import com.example.pidev.utils.UserSession;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.fxml.FXML;
import javafx.fxml.Initializable;
import javafx.scene.control.*;
import javafx.scene.image.Image;
import javafx.scene.image.ImageView;
import javafx.scene.layout.StackPane;
import javafx.scene.shape.Circle;
import javafx.scene.text.Text;
import javafx.stage.FileChooser;

import java.io.File;
import java.net.URL;
import java.sql.SQLException;
import java.time.LocalDateTime;
import java.time.format.DateTimeFormatter;
import java.util.ResourceBundle;

public class ProfilController implements Initializable {

    // Champs du profil
    @FXML private TextField firstNameField;
    @FXML private TextField lastNameField;
    @FXML private TextField emailField;
    @FXML private TextField phoneField;
    @FXML private ComboBox<String> facultyComboBox;
    @FXML private ComboBox<String> roleComboBox;
    @FXML private TextField registrationDateField;
    @FXML private TextArea bioTextArea;
    @FXML private Label bioCharCountLabel;

    // Photo de profil
    @FXML private StackPane avatarContainer;
    @FXML private ImageView profileImageView;
    @FXML private StackPane initialsContainer;
    @FXML private Text userInitialsText;
    @FXML private Button uploadImageButton;

    // Sécurité
    @FXML private TextField currentPasswordField;
    @FXML private PasswordField newPasswordField;
    @FXML private PasswordField confirmPasswordField;

    // Statistiques
    @FXML private Label eventCountLabel;  // ← Label pour le nombre d'événements

    @FXML private Label verificationStatusLabel;
    @FXML private Label lastLoginLabel;
    @FXML private Label userRoleDisplayLabel;
    @FXML private Label userLevelLabel;

    private UserModel currentUser;
    private UserService userService;
    private RoleService roleService;
    private EventService eventService;  // ← AJOUTER CETTE VARIABLE
    private MainController mainController;

    public void setMainController(MainController mainController) {
        this.mainController = mainController;
    }

    @Override
    public void initialize(URL location, ResourceBundle resources) {
        try {
            System.out.println("✅ ProfilController initialisé");
            userService = new UserService();
            roleService = new RoleService();
            eventService = new EventService();  // ← INITIALISER LE SERVICE

            // Récupérer l'utilisateur connecté depuis la session
            currentUser = UserSession.getInstance().getCurrentUser();

            if (currentUser != null) {
                // Afficher les infos dans la console
                System.out.println("📌 Faculté de l'utilisateur: " + currentUser.getFaculte());
                System.out.println("📌 Rôle de l'utilisateur: " +
                        (currentUser.getRole() != null ? currentUser.getRole().getRoleName() : "Non défini"));

                // Charger les données
                loadUserDataFromModel();
                loadFacultiesFromDatabase();
                loadRolesFromDatabase();
                loadProfileImage();
                updateStatistics();      // ← MET À JOUR LES STATISTIQUES
                setupBioCounter();
                disableReadOnlyFields();

            } else {
                System.err.println("❌ Aucun utilisateur connecté");
                showAlert("Erreur", "Aucun utilisateur connecté");
            }

        } catch (Exception e) {
            e.printStackTrace();
            showAlert("Erreur", "Erreur lors de l'initialisation: " + e.getMessage());
        }
    }

    /**
     * Met à jour les statistiques du profil
     */
    private void updateStatistics() {
        if (currentUser != null) {
            // Compter les événements créés par l'utilisateur
            int eventCount = countUserEvents();
            eventCountLabel.setText(String.valueOf(eventCount));

            // Mettre à jour les autres statistiques
            if (userLevelLabel != null && currentUser.getRole() != null) {
                userLevelLabel.setText(currentUser.getRole().getRoleName());
            }

            verificationStatusLabel.setText("🟢 Compte vérifié");
            lastLoginLabel.setText("Dernière connexion: " +
                    LocalDateTime.now().format(DateTimeFormatter.ofPattern("dd/MM/yyyy HH:mm")));
        }
    }

    /**
     * Compte le nombre d'événements créés par l'utilisateur
     */
    private int countUserEvents() {
        try {
            // Utiliser la méthode countEvents() qui compte tous les événements
            int totalEvents = eventService.countEvents();
            System.out.println("📊 Nombre total d'événements: " + totalEvents);
            return totalEvents;

            // Si vous voulez compter uniquement les événements créés par l'utilisateur connecté,
            // vous devrez ajouter une méthode spécifique dans EventService, comme:
            // return eventService.countEventsByUser(currentUser.getId_User());

        } catch (Exception e) {
            System.err.println("❌ Erreur lors du comptage des événements: " + e.getMessage());
            e.printStackTrace();
            return 0;
        }
    }

    // === LE RESTE DE VOTRE CODE RESTE IDENTIQUE ===

    /**
     * Charge les facultés depuis la table user_model
     */
    private void loadFacultiesFromDatabase() {
        try {
            ObservableList<String> faculties = userService.getAllFacultes();

            if (faculties.isEmpty()) {
                System.out.println("⚠️ Aucune faculté trouvée dans la base de données");
                faculties.add("Non définie");
            } else {
                System.out.println("✅ " + faculties.size() + " facultés chargées depuis la base");
            }

            facultyComboBox.setItems(faculties);

            String userFaculty = currentUser.getFaculte();
            if (userFaculty != null && !userFaculty.isEmpty()) {
                if (faculties.contains(userFaculty)) {
                    facultyComboBox.setValue(userFaculty);
                    System.out.println("✅ Faculté sélectionnée: " + userFaculty);
                } else {
                    facultyComboBox.getItems().add(userFaculty);
                    facultyComboBox.setValue(userFaculty);
                    System.out.println("➕ Faculté ajoutée: " + userFaculty);
                }
            }

            facultyComboBox.valueProperty().addListener((obs, oldVal, newVal) -> {
                if (newVal != null && !newVal.equals(oldVal)) {
                    System.out.println("Faculté changée: " + oldVal + " -> " + newVal);
                    currentUser.setFaculte(newVal);
                }
            });

        } catch (Exception e) {
            System.err.println("❌ Erreur chargement facultés: " + e.getMessage());
            e.printStackTrace();
        }
    }

    /**
     * Charge les rôles depuis la table role
     */
    private void loadRolesFromDatabase() {
        try {
            ObservableList<String> roles = roleService.getAllRoleNames();

            if (roles.isEmpty()) {
                System.out.println("⚠️ Aucun rôle trouvé dans la base de données");
                roles.add("Non défini");
            } else {
                System.out.println("✅ " + roles.size() + " rôles chargés depuis la base");
            }

            roleComboBox.setItems(roles);

            if (currentUser.getRole() != null) {
                String userRole = currentUser.getRole().getRoleName();
                if (userRole != null && !userRole.isEmpty()) {
                    if (roles.contains(userRole)) {
                        roleComboBox.setValue(userRole);
                        System.out.println("✅ Rôle sélectionné: " + userRole);
                    }
                }
            }

            roleComboBox.setDisable(true);

        } catch (Exception e) {
            System.err.println("❌ Erreur chargement rôles: " + e.getMessage());
            e.printStackTrace();
        }
    }

    /**
     * Charge les données depuis UserModel
     */
    private void loadUserDataFromModel() {
        if (currentUser != null) {
            firstNameField.setText(currentUser.getFirst_Name());
            lastNameField.setText(currentUser.getLast_Name());
            emailField.setText(currentUser.getEmail());
            phoneField.setText(currentUser.getPhone() != null ? currentUser.getPhone() : "");

            if (currentUser.getRegistrationDate() != null) {
                DateTimeFormatter formatter = DateTimeFormatter.ofPattern("dd/MM/yyyy HH:mm");
                registrationDateField.setText(currentUser.getRegistrationDate().format(formatter));
            } else {
                registrationDateField.setText("Non disponible");
            }

            bioTextArea.setText(currentUser.getBio() != null ? currentUser.getBio() : "");
            if (bioCharCountLabel != null) {
                bioCharCountLabel.setText((currentUser.getBio() != null ? currentUser.getBio().length() : 0) + "/500");
            }

            if (currentPasswordField != null && currentUser.getPassword() != null) {
                currentPasswordField.setText(currentUser.getPassword());
            }

            if (userRoleDisplayLabel != null && currentUser.getRole() != null) {
                userRoleDisplayLabel.setText(currentUser.getRole().getRoleName());
            }
        }
    }

    /**
     * Désactive les champs en lecture seule
     */
    private void disableReadOnlyFields() {
        if (roleComboBox != null) {
            roleComboBox.setDisable(true);
            roleComboBox.setStyle("-fx-background-color: #f1f5f9; -fx-text-fill: #64748b;");
        }

        if (registrationDateField != null) {
            registrationDateField.setDisable(true);
            registrationDateField.setStyle("-fx-background-color: #f1f5f9; -fx-text-fill: #64748b;");
        }
    }

    private void setupBioCounter() {
        bioTextArea.textProperty().addListener((obs, oldVal, newVal) -> {
            int length = newVal.length();
            bioCharCountLabel.setText(length + "/500");

            if (length > 500) {
                bioTextArea.setText(oldVal);
                bioCharCountLabel.setText("500/500 (maximum atteint)");
            }
        });
    }

    /**
     * Charge l'image de profil avec le style circulaire (initiales par défaut)
     */
    private void loadProfileImage() {
        UserSession session = UserSession.getInstance();

        if (currentUser != null) {
            if (userInitialsText != null) {
                userInitialsText.setText(session.getInitials());
            }

            String photoUrl = currentUser.getProfilePictureUrl();
            if (photoUrl != null && !photoUrl.isEmpty()) {
                try {
                    Image image = new Image(photoUrl, 132, 132, true, true);
                    profileImageView.setImage(image);
                    profileImageView.setVisible(true);

                    if (initialsContainer != null) {
                        initialsContainer.setVisible(false);
                    }

                    applyCircularClip(profileImageView, 66);
                    System.out.println("✅ Photo de profil chargée depuis: " + photoUrl);

                } catch (Exception e) {
                    System.err.println("❌ Erreur chargement photo: " + e.getMessage());
                    profileImageView.setVisible(false);
                    if (initialsContainer != null) {
                        initialsContainer.setVisible(true);
                    }
                }
            } else {
                System.out.println("ℹ️ Aucune photo de profil, affichage des initiales: " + session.getInitials());
                profileImageView.setVisible(false);
                if (initialsContainer != null) {
                    initialsContainer.setVisible(true);
                }
            }
        }
    }

    /**
     * Applique un clip circulaire à l'image
     */
    private void applyCircularClip(ImageView imageView, double radius) {
        if (imageView != null && imageView.getImage() != null) {
            Circle clip = new Circle(radius, radius, radius);
            imageView.setClip(clip);
            imageView.setPreserveRatio(true);
            System.out.println("✅ Clip circulaire appliqué (rayon: " + radius + ")");
        }
    }

    @FXML
    private void uploadProfileImage() {
        FileChooser fileChooser = new FileChooser();
        fileChooser.setTitle("Sélectionner une photo de profil");
        fileChooser.getExtensionFilters().addAll(
                new FileChooser.ExtensionFilter("Images", "*.png", "*.jpg", "*.jpeg", "*.gif")
        );

        File selectedImageFile = fileChooser.showOpenDialog(uploadImageButton.getScene().getWindow());

        if (selectedImageFile != null) {
            if (selectedImageFile.length() > 5 * 1024 * 1024) {
                showAlert("Fichier trop volumineux", "La taille maximale est de 5MB.");
                return;
            }

            try {
                Image image = new Image(selectedImageFile.toURI().toString(), 132, 132, true, true);
                profileImageView.setImage(image);
                profileImageView.setVisible(true);
                if (initialsContainer != null) {
                    initialsContainer.setVisible(false);
                }

                applyCircularClip(profileImageView, 66);
                currentUser.setProfilePictureUrl(selectedImageFile.toURI().toString());
                System.out.println("✅ Image chargée: " + selectedImageFile.getName());

            } catch (Exception e) {
                showAlert("Erreur", "Impossible de charger l'image: " + e.getMessage());
                e.printStackTrace();
            }
        }
    }

    @FXML
    private void saveProfile() {
        currentUser.setFirst_Name(firstNameField.getText().trim());
        currentUser.setLast_Name(lastNameField.getText().trim());
        currentUser.setEmail(emailField.getText().trim());
        currentUser.setPhone(phoneField.getText().trim());
        currentUser.setFaculte(facultyComboBox.getValue());
        currentUser.setBio(bioTextArea.getText().trim());

        try {
            if (userService.updateUser(currentUser)) {
                UserSession.getInstance().updateUserInfo(currentUser);

                if (mainController != null) {
                    mainController.refreshHeaderProfile();
                    System.out.println("🔄 Header rafraîchi après modification du profil");
                } else {
                    System.err.println("⚠️ mainController est null, impossible de rafraîchir le header");
                }

                showSuccessAlert("Succès", "Profil mis à jour avec succès");
            } else {
                showAlert("Erreur", "Échec de la mise à jour");
            }
        } catch (Exception e) {
            e.printStackTrace();
            showAlert("Erreur", "Erreur lors de la sauvegarde: " + e.getMessage());
        }
    }

    @FXML
    private void cancelChanges() {
        loadUserDataFromModel();
        loadProfileImage();
        if (currentUser.getFaculte() != null) {
            facultyComboBox.setValue(currentUser.getFaculte());
        }
        showAlert("Annulé", "Modifications annulées");
    }

    @FXML
    private void changePassword() {
        String current = currentPasswordField.getText();
        String newPass = newPasswordField.getText();
        String confirm = confirmPasswordField.getText();

        if (current.isEmpty() || newPass.isEmpty() || confirm.isEmpty()) {
            showAlert("Champs requis", "Tous les champs sont obligatoires");
            return;
        }

        if (!newPass.equals(confirm)) {
            showAlert("Erreur", "Les nouveaux mots de passe ne correspondent pas");
            return;
        }

        if (newPass.length() < 6) {
            showAlert("Erreur", "Le mot de passe doit contenir au moins 6 caractères");
            return;
        }

        if (!current.equals(currentUser.getPassword())) {
            showAlert("Erreur", "Mot de passe actuel incorrect");
            return;
        }

        try {
            currentUser.setPassword(newPass);
            if (userService.updateUser(currentUser)) {
                showSuccessAlert("Succès", "Mot de passe changé avec succès");
                newPasswordField.clear();
                confirmPasswordField.clear();
                currentPasswordField.setText(newPass);
            }
        } catch (Exception e) {
            showAlert("Erreur", "Erreur lors du changement: " + e.getMessage());
        }
    }

    private void showAlert(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.WARNING);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }

    private void showSuccessAlert(String title, String message) {
        Alert alert = new Alert(Alert.AlertType.INFORMATION);
        alert.setTitle(title);
        alert.setHeaderText(null);
        alert.setContentText(message);
        alert.showAndWait();
    }
}