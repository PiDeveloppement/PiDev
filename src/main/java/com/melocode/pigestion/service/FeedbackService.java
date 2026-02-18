package com.melocode.pigestion.service;

import com.melocode.pigestion.model.FeedbackStats;
import com.melocode.pigestion.model.Question;
import com.melocode.pigestion.utils.MyConnection;
import java.sql.*;
import java.util.ArrayList;
import java.util.List;

public class FeedbackService {
    private Connection conn = MyConnection.getInstance().getCnx();

    /**
     * Charge les questions (Inchangé)
     */
    public List<Question> chargerQuestionsAleatoires(int idEvent) throws SQLException {
        List<Question> questions = new ArrayList<>();
        String req = "SELECT * FROM questions WHERE id_event = ? ORDER BY RAND() LIMIT 10";
        PreparedStatement pst = conn.prepareStatement(req);
        pst.setInt(1, idEvent);
        ResultSet rs = pst.executeQuery();
        while (rs.next()) {
            questions.add(new Question(rs.getInt(1), rs.getInt(2), rs.getString(3), rs.getString(4), rs.getInt(5)));
        }
        return questions;
    }

    /**
     * Enregistre le feedback (Inchangé)
     */
    public void enregistrerFeedbackComplet(int idUser, int idQuest, String rep, String comm, int stars) throws SQLException {
        String req = "INSERT INTO feedbacks (id_user, id_question, reponse_donnee, comments, etoiles) VALUES (?, ?, ?, ?, ?)";
        PreparedStatement pst = conn.prepareStatement(req);
        pst.setInt(1, idUser);
        pst.setInt(2, idQuest);
        pst.setString(3, rep);
        pst.setString(4, comm);
        pst.setInt(5, stars);
        pst.executeUpdate();
    }

    /**
     * RÉCUPÈRE UN SEUL TÉMOIGNAGE PAR UTILISATEUR
     * Correction pour SQL_MODE = only_full_group_by
     */
    public List<FeedbackStats> recupererHistorique() throws SQLException {
        List<FeedbackStats> historique = new ArrayList<>();

        // On utilise MAX() sur id_feedback et comments pour satisfaire la rigueur de MySQL
        String req = "SELECT MAX(f.id_feedback) as id_f, MAX(f.comments) as comm, MAX(f.etoiles) as stars, u.username " +
                "FROM feedbacks f " +
                "JOIN users u ON f.id_user = u.id_user " +
                "GROUP BY u.username";

        PreparedStatement pst = conn.prepareStatement(req);
        ResultSet rs = pst.executeQuery();

        while (rs.next()) {
            historique.add(new FeedbackStats(
                    rs.getInt("id_f"),
                    rs.getString("username"),
                    rs.getString("comm") != null ? rs.getString("comm") : "Pas de commentaire",
                    "N/A",
                    rs.getInt("stars")
            ));
        }
        return historique;
    }

    public void modifierFeedback(int idUser, String comm, int stars) throws SQLException {
        String req = "UPDATE feedbacks SET comments = ?, etoiles = ? WHERE id_user = ?";
        PreparedStatement pst = conn.prepareStatement(req);
        pst.setString(1, comm);
        pst.setInt(2, stars);
        pst.setInt(3, idUser);
        pst.executeUpdate();
    }

    public void supprimerFeedback(int idUser) throws SQLException {
        String req = "DELETE FROM feedbacks WHERE id_user = ?";
        PreparedStatement pst = conn.prepareStatement(req);
        pst.setInt(1, idUser);
        pst.executeUpdate();
    }
}