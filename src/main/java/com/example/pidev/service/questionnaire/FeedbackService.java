package com.example.pidev.service.questionnaire;


import com.example.pidev.model.questionnaire.FeedbackStats;
import com.example.pidev.model.questionnaire.Question;
import com.example.pidev.utils.DBConnection;

import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class FeedbackService {
    // Utilisation du nom 'conn' comme défini ici
    private final Connection conn = DBConnection.getInstance().getCnx();

    public List<Question> chargerQuestionsAleatoires(int idEvent) throws SQLException {
        List<Question> questions = new ArrayList<>();
        String req = "SELECT id_question, id_event, texte_question, bonne_reponse, points FROM questions WHERE id_event = ? ORDER BY RAND() LIMIT 10";

        try (PreparedStatement pst = conn.prepareStatement(req)) {
            pst.setInt(1, idEvent);
            try (ResultSet rs = pst.executeQuery()) {
                while (rs.next()) {
                    questions.add(new Question(
                            rs.getInt("id_question"),
                            rs.getInt("id_event"),
                            rs.getString("texte_question"),
                            rs.getString("bonne_reponse"),
                            rs.getInt("points")
                    ));
                }
            }
        }
        return questions;
    }

    public int enregistrerFeedbackComplet(int idUser, int idQuest, String rep, String comm, int stars) throws SQLException {
        String req = "INSERT INTO feedbacks (id_user, id_question, reponse_donnee, comments, etoiles) VALUES (?, ?, ?, ?, ?)";
        // On ajoute Statement.RETURN_GENERATED_KEYS pour récupérer l'ID
        try (PreparedStatement pst = conn.prepareStatement(req, Statement.RETURN_GENERATED_KEYS)) {
            pst.setInt(1, idUser);
            pst.setInt(2, idQuest);
            pst.setString(3, rep);
            pst.setString(4, comm);
            pst.setInt(5, stars);
            pst.executeUpdate();

            // Récupération de l'ID auto-incrémenté
            try (ResultSet rs = pst.getGeneratedKeys()) {
                if (rs.next()) {
                    return rs.getInt(1);
                }
            }
        }
        return 0;
    }

    public List<FeedbackStats> recupererHistorique() throws SQLException {
        List<FeedbackStats> historique = new ArrayList<>();
        String req = "SELECT f.id_feedback, f.comments, f.etoiles, u.username " +
                "FROM feedbacks f " +
                "JOIN users u ON f.id_user = u.id_user";

        try (Statement st = conn.createStatement();
             ResultSet rs = st.executeQuery(req)) {
            while (rs.next()) {
                historique.add(new FeedbackStats(
                        rs.getInt("id_feedback"),
                        rs.getString("username"),
                        rs.getString("comments") != null ? rs.getString("comments") : "Pas de commentaire",
                        "N/A",
                        rs.getInt("etoiles")
                ));
            }
        }
        return historique;
    }

    /**
     * CORRECTION : Modification par ID de feedback
     * Table harmonisée sur 'feedbacks' et variable 'conn' utilisée
     */
    public void modifierFeedback(int idFeedback, String comment, int stars) throws SQLException {
        // Utilisation de 'conn' (la variable de la ligne 11) et de la table 'feedbacks'
        String query = "UPDATE feedbacks SET comments = ?, etoiles = ? WHERE id_feedback = ?";
        try (PreparedStatement pst = conn.prepareStatement(query)) {
            pst.setString(1, comment);
            pst.setInt(2, stars);
            pst.setInt(3, idFeedback);
            pst.executeUpdate();
        }
    }

    /**
     * CORRECTION : Suppression par ID de feedback
     */
    public void supprimerFeedback(int idFeedback) throws SQLException {
        String query = "DELETE FROM feedbacks WHERE id_feedback = ?";
        try (PreparedStatement pst = conn.prepareStatement(query)) {
            pst.setInt(1, idFeedback);
            pst.executeUpdate();
        }
    }
}