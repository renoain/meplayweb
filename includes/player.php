<div class="music-player" id="musicPlayer">
    <audio id="audioElement" preload="metadata"></audio>
    
    <div class="player-container">
        <!-- Player Left - Song Info -->
        <div class="player-left">
            <img id="nowPlayingCover" src="assets/images/covers/default-cover.png" alt="Now Playing" class="now-playing-cover">
            <div class="now-playing-info">
                <div id="nowPlayingTitle" class="now-playing-title">Tidak ada lagu</div>
                <div id="nowPlayingArtist" class="now-playing-artist">Pilih lagu untuk diputar</div>
                <div class="now-playing-actions">
                    <button id="nowPlayingLike" class="like-btn" title="Like">
                        <i class="far fa-heart"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Player Center - Controls -->
        <div class="player-center">
            <div class="player-controls">
                <button id="shuffleBtn" class="control-btn" title="Shuffle">
                    <i class="fas fa-random"></i>
                </button>
                <button id="prevBtn" class="control-btn" title="Previous">
                    <i class="fas fa-step-backward"></i>
                </button>
                <button id="playPauseBtn" class="control-btn play-pause" title="Play">
                    <i class="fas fa-play"></i>
                </button>
                <button id="nextBtn" class="control-btn" title="Next">
                    <i class="fas fa-step-forward"></i>
                </button>
                <button id="repeatBtn" class="control-btn repeat-none" title="Repeat">
                    <i class="fas fa-repeat"></i>
                </button>
            </div>
            
            <div class="progress-container">
                <span id="currentTime" class="time-display">0:00</span>
                <div id="progressBar" class="progress-bar">
                    <div id="progress" class="progress"></div>
                    <div id="progressHandle" class="progress-handle"></div>
                </div>
                <span id="totalTime" class="time-display">0:00</span>
            </div>
        </div>

        <!-- Player Right - Additional Controls -->
        <div class="player-right">
            <div class="volume-controls">
                <button id="volumeBtn" class="control-btn" title="Volume">
                    <i class="fas fa-volume-up"></i>
                </button>
                <div class="volume-container">
                    <div id="volumeBar" class="volume-bar">
                        <div id="volumeProgress" class="volume-progress"></div>
                        <div id="volumeHandle" class="volume-handle"></div>
                    </div>
                </div>
            </div>
            
            <button id="queueBtn" class="queue-btn" title="Queue">
                <i class="fas fa-list"></i>
            </button>
        </div>
    </div>

    <!-- Queue Modal -->
    <div id="queueModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Playback Queue</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="queueList" class="queue-list">
                    <!-- Queue items will be populated here -->
                </div>
                <div class="queue-actions">
                    <button id="clearQueue" class="btn-secondary">Clear Queue</button>
                </div>
            </div>
        </div>
    </div>
</div>