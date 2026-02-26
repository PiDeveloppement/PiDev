package com.example.pidev.service.search;

import org.apache.lucene.analysis.Analyzer;
import org.apache.lucene.analysis.standard.StandardAnalyzer;
import org.apache.lucene.document.Document;
import org.apache.lucene.document.Field;
import org.apache.lucene.document.StringField;
import org.apache.lucene.document.TextField;
import org.apache.lucene.index.*;
import org.apache.lucene.queryparser.classic.QueryParser;
import org.apache.lucene.search.*;
import org.apache.lucene.store.Directory;
import org.apache.lucene.store.FSDirectory;

import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.ArrayList;
import java.util.List;

public class LuceneSearchService {

    private static final String INDEX_DIR = "lucene_index";
    private static Directory directory;
    private static Analyzer analyzer;

    static {
        try {
            Path indexPath = Paths.get(INDEX_DIR);
            if (!Files.exists(indexPath)) {
                Files.createDirectories(indexPath);
            }
            directory = FSDirectory.open(indexPath);
            analyzer = new StandardAnalyzer();
        } catch (IOException e) {
            e.printStackTrace();
        }
    }

    // ==================== INDEXATION ====================

    public static void indexSponsor(int id, String company, String email, double amount, int eventId) {
        try {
            IndexWriterConfig config = new IndexWriterConfig(analyzer);
            IndexWriter writer = new IndexWriter(directory, config);

            Document doc = new Document();
            doc.add(new StringField("id", String.valueOf(id), Field.Store.YES));
            doc.add(new TextField("company", company, Field.Store.YES));
            doc.add(new TextField("email", email, Field.Store.YES));
            doc.add(new TextField("amount", String.valueOf(amount), Field.Store.YES));
            doc.add(new StringField("eventId", String.valueOf(eventId), Field.Store.YES));

            writer.updateDocument(new Term("id", String.valueOf(id)), doc);
            writer.close();
        } catch (IOException e) {
            e.printStackTrace();
        }
    }

    public static void deleteSponsor(int id) {
        try {
            IndexWriterConfig config = new IndexWriterConfig(analyzer);
            IndexWriter writer = new IndexWriter(directory, config);
            writer.deleteDocuments(new Term("id", String.valueOf(id)));
            writer.close();
        } catch (IOException e) {
            e.printStackTrace();
        }
    }

    // ==================== RECHERCHE ====================

    public static List<Integer> searchSponsors(String query) {
        List<Integer> results = new ArrayList<>();
        try {
            IndexReader reader = DirectoryReader.open(directory);
            IndexSearcher searcher = new IndexSearcher(reader);

            QueryParser parser = new QueryParser("company", analyzer);
            Query q = parser.parse(query + "*"); // recherche par préfixe

            TopDocs topDocs = searcher.search(q, 100);
            for (ScoreDoc scoreDoc : topDocs.scoreDocs) {
                Document doc = searcher.doc(scoreDoc.doc);
                int id = Integer.parseInt(doc.get("id"));
                results.add(id);
            }
            reader.close();
        } catch (Exception e) {
            e.printStackTrace();
        }
        return results;
    }

    public static List<Integer> searchByField(String field, String value) {
        List<Integer> results = new ArrayList<>();
        try {
            IndexReader reader = DirectoryReader.open(directory);
            IndexSearcher searcher = new IndexSearcher(reader);

            QueryParser parser = new QueryParser(field, analyzer);
            Query q = parser.parse(value);

            TopDocs topDocs = searcher.search(q, 100);
            for (ScoreDoc scoreDoc : topDocs.scoreDocs) {
                Document doc = searcher.doc(scoreDoc.doc);
                results.add(Integer.parseInt(doc.get("id")));
            }
            reader.close();
        } catch (Exception e) {
            e.printStackTrace();
        }
        return results;
    }
}